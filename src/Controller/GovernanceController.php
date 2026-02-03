<?php

namespace App\Controller;

use App\Entity\Proposal;
use App\Entity\Vote;
use App\Repository\ProposalRepository;
use App\Repository\VoteRepository;
use App\Repository\EligibilityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/governance')]
class GovernanceController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ProposalRepository $proposalRepo,
        private VoteRepository $voteRepo,
        private EligibilityRepository $eligibilityRepo
    ) {}

    /**
     * Get latest proposals for homepage (public)
     */
    #[Route('/proposals/latest', methods: ['GET'])]
    public function getLatestProposals(Request $request): JsonResponse
    {
        $limit = min((int) $request->query->get('limit', 5), 10);
        $proposals = $this->proposalRepo->findLatestProposals($limit);

        return $this->json([
            'proposals' => array_map(fn(Proposal $p) => $p->toArray(), $proposals)
        ]);
    }

    /**
     * Get all proposals (public, excludes drafts)
     */
    #[Route('/proposals', methods: ['GET'])]
    public function getAllProposals(): JsonResponse
    {
        $proposals = $this->proposalRepo->findAllPublic();

        return $this->json([
            'proposals' => array_map(fn(Proposal $p) => $p->toArray(), $proposals)
        ]);
    }

    /**
     * Get single proposal by ID
     */
    #[Route('/proposals/{id}', methods: ['GET'])]
    public function getProposal(int $id): JsonResponse
    {
        $proposal = $this->proposalRepo->find($id);

        if (!$proposal || $proposal->getStatus() === Proposal::STATUS_DRAFT) {
            return $this->json(['error' => 'Proposal not found'], 404);
        }

        return $this->json($proposal->toArray());
    }

    /**
     * Check if address has voted on a proposal
     */
    #[Route('/proposals/{id}/vote-status/{kiAddress}', methods: ['GET'])]
    public function checkVoteStatus(int $id, string $kiAddress): JsonResponse
    {
        $proposal = $this->proposalRepo->find($id);

        if (!$proposal) {
            return $this->json(['error' => 'Proposal not found'], 404);
        }

        $vote = $this->voteRepo->findVoteByAddressAndProposal($kiAddress, $proposal);
        $eligibility = $this->eligibilityRepo->findOneBy(['kiAddress' => $kiAddress]);

        return $this->json([
            'hasVoted' => $vote !== null,
            'vote' => $vote?->toArray(),
            'votingPower' => $eligibility?->getBalance() ?? '0',
            'canVote' => $proposal->isActive() && $vote === null && $eligibility !== null,
        ]);
    }

    /**
     * Submit a vote (requires signature)
     */
    #[Route('/proposals/{id}/vote', methods: ['POST'])]
    public function submitVote(int $id, Request $request): JsonResponse
    {
        $proposal = $this->proposalRepo->find($id);

        if (!$proposal) {
            return $this->json(['error' => 'Proposal not found'], 404);
        }

        if (!$proposal->isActive()) {
            return $this->json(['error' => 'Proposal is not active for voting'], 400);
        }

        $data = json_decode($request->getContent(), true);
        $kiAddress = $data['kiAddress'] ?? null;
        $voteChoice = $data['voteChoice'] ?? null;
        $signature = $data['signature'] ?? null;
        $pubKey = $data['pubKey'] ?? null;

        if (!$kiAddress || !$voteChoice || !$signature || !$pubKey) {
            return $this->json(['error' => 'Missing required fields'], 400);
        }

        if (!in_array($voteChoice, [Vote::VOTE_FOR, Vote::VOTE_AGAINST, Vote::VOTE_ABSTAIN])) {
            return $this->json(['error' => 'Invalid vote choice'], 400);
        }

        // Check if already voted
        if ($this->voteRepo->hasVoted($kiAddress, $proposal)) {
            return $this->json(['error' => 'You have already voted on this proposal'], 400);
        }

        // Get voting power from eligibility
        $eligibility = $this->eligibilityRepo->findOneBy(['kiAddress' => $kiAddress]);
        if (!$eligibility) {
            return $this->json(['error' => 'Address not eligible to vote'], 400);
        }

        $votingPower = $eligibility->getBalance();

        // TODO: Verify signature (for now, trust the signature)
        // In production, verify the Keplr signature against the pubKey

        // Create vote
        $vote = new Vote();
        $vote->setProposal($proposal)
            ->setKiAddress($kiAddress)
            ->setVoteChoice($voteChoice)
            ->setVotingPower($votingPower)
            ->setSignature($signature)
            ->setPubKey($pubKey);

        // Update proposal tallies
        switch ($voteChoice) {
            case Vote::VOTE_FOR:
                $proposal->addVotesFor($votingPower);
                break;
            case Vote::VOTE_AGAINST:
                $proposal->addVotesAgainst($votingPower);
                break;
            case Vote::VOTE_ABSTAIN:
                $proposal->addVotesAbstain($votingPower);
                break;
        }
        $proposal->incrementVoterCount();

        $this->em->persist($vote);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'vote' => $vote->toArray(),
            'proposal' => $proposal->toArray(),
        ]);
    }

    /**
     * Get votes for a proposal (admin or detailed view)
     */
    #[Route('/proposals/{id}/votes', methods: ['GET'])]
    public function getProposalVotes(int $id, Request $request): JsonResponse
    {
        $proposal = $this->proposalRepo->find($id);

        if (!$proposal) {
            return $this->json(['error' => 'Proposal not found'], 404);
        }

        $votes = $this->voteRepo->findVotesByProposal($proposal);

        return $this->json([
            'proposal' => $proposal->toArray(),
            'votes' => array_map(fn(Vote $v) => $v->toArray(), $votes),
            'stats' => $this->voteRepo->getVoteStatsByProposal($proposal),
        ]);
    }

    // ============== ADMIN ENDPOINTS ==============

    /**
     * Admin: Get all proposals including drafts
     */
    #[Route('/admin/proposals', methods: ['GET'])]
    public function adminGetAllProposals(): JsonResponse
    {
        $proposals = $this->proposalRepo->findAllAdmin();

        return $this->json([
            'proposals' => array_map(fn(Proposal $p) => $p->toArray(), $proposals)
        ]);
    }

    /**
     * Admin: Create new proposal
     */
    #[Route('/admin/proposals', methods: ['POST'])]
    public function adminCreateProposal(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $title = $data['title'] ?? null;
            $description = $data['description'] ?? null;
            $startDate = $data['startDate'] ?? null;
            $endDate = $data['endDate'] ?? null;
            $status = $data['status'] ?? Proposal::STATUS_DRAFT;
            $quorum = $data['quorum'] ?? '0';

            if (!$title || !$description || !$startDate || !$endDate) {
                return $this->json(['error' => 'Missing required fields'], 400);
            }

            $proposal = new Proposal();
            $proposal->setTitle($title)
                ->setDescription($description)
                ->setStartDate(new \DateTime($startDate))
                ->setEndDate(new \DateTime($endDate))
                ->setStatus($status)
                ->setQuorum($quorum)
                ->setProposalNumber($this->proposalRepo->getNextProposalNumber());

            $this->em->persist($proposal);
            $this->em->flush();

            return $this->json([
                'success' => true,
                'proposal' => $proposal->toArray(),
            ], 201);
        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Admin: Update proposal
     */
    #[Route('/admin/proposals/{id}', methods: ['PUT', 'PATCH'])]
    public function adminUpdateProposal(int $id, Request $request): JsonResponse
    {
        $proposal = $this->proposalRepo->find($id);

        if (!$proposal) {
            return $this->json(['error' => 'Proposal not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['title'])) $proposal->setTitle($data['title']);
        if (isset($data['description'])) $proposal->setDescription($data['description']);
        if (isset($data['startDate'])) $proposal->setStartDate(new \DateTime($data['startDate']));
        if (isset($data['endDate'])) $proposal->setEndDate(new \DateTime($data['endDate']));
        if (isset($data['status'])) $proposal->setStatus($data['status']);
        if (isset($data['quorum'])) $proposal->setQuorum($data['quorum']);

        $this->em->flush();

        return $this->json([
            'success' => true,
            'proposal' => $proposal->toArray(),
        ]);
    }

    /**
     * Admin: Delete proposal
     */
    #[Route('/admin/proposals/{id}', methods: ['DELETE'])]
    public function adminDeleteProposal(int $id): JsonResponse
    {
        $proposal = $this->proposalRepo->find($id);

        if (!$proposal) {
            return $this->json(['error' => 'Proposal not found'], 404);
        }

        // Only allow deleting drafts
        if ($proposal->getStatus() !== Proposal::STATUS_DRAFT) {
            return $this->json(['error' => 'Can only delete draft proposals'], 400);
        }

        $this->em->remove($proposal);
        $this->em->flush();

        return $this->json(['success' => true]);
    }

    /**
     * Admin: Finalize proposal (compute result)
     */
    #[Route('/admin/proposals/{id}/finalize', methods: ['POST'])]
    public function adminFinalizeProposal(int $id): JsonResponse
    {
        $proposal = $this->proposalRepo->find($id);

        if (!$proposal) {
            return $this->json(['error' => 'Proposal not found'], 404);
        }

        if ($proposal->getStatus() !== Proposal::STATUS_ENDED && $proposal->getStatus() !== Proposal::STATUS_ACTIVE) {
            return $this->json(['error' => 'Proposal must be ended or active to finalize'], 400);
        }

        // Determine result
        $votesFor = $proposal->getVotesFor();
        $votesAgainst = $proposal->getVotesAgainst();

        if (bccomp($votesFor, $votesAgainst, 0) > 0) {
            $proposal->setStatus(Proposal::STATUS_PASSED);
        } else {
            $proposal->setStatus(Proposal::STATUS_REJECTED);
        }

        $this->em->flush();

        return $this->json([
            'success' => true,
            'proposal' => $proposal->toArray(),
        ]);
    }
}
