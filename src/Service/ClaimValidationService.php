<?php

namespace App\Service;

use App\Entity\Claim;
use App\Repository\ClaimRepository;

/**
 * Batch validation of claims — verifies Cosmos signatures and updates statuses.
 */
class ClaimValidationService
{
    public function __construct(
        private ClaimRepository $claimRepository,
        private CosmosSignatureService $cosmosSignatureService
    ) {
    }

    /**
     * Validate a single claim's signature.
     *
     * @return array{valid: bool, reason: string}
     */
    public function validateClaim(Claim $claim): array
    {
        $signature = $claim->getSignature();
        $pubKey = $claim->getPubKey();
        $kiAddress = $claim->getKiAddress();
        $ethAddress = $claim->getEthAddress();

        // Check required fields
        if (empty($signature) || empty($pubKey)) {
            return ['valid' => false, 'reason' => 'Missing signature or pubKey'];
        }

        if (empty($kiAddress)) {
            return ['valid' => false, 'reason' => 'Missing Ki address'];
        }

        if (empty($ethAddress) || !preg_match('/^0x[a-fA-F0-9]{40}$/', $ethAddress)) {
            return ['valid' => false, 'reason' => 'Invalid ETH address format'];
        }

        // Reconstruct the message that was signed
        // The frontend signs: state.message which comes from /api/claim/prepare
        // That returns: "Ready to claim. Please sign the message with your Ki wallet."
        // But if the nonce was used in the message, we need to check both variants
        $messageCandidates = [
            'Ready to claim. Please sign the message with your Ki wallet.',
            'Sign to claim XKI tokens', // fallback from frontend demo mode
            'Sign this message to claim your XKI tokens', // another fallback
        ];

        // Also try nonce-based message if nonce exists
        $nonce = $claim->getNonce();
        if ($nonce) {
            // Some versions may have included the nonce in the signed message
            array_unshift($messageCandidates, "Claim XKI tokens\nNonce: {$nonce}");
            array_unshift($messageCandidates, "XKI Migration Claim\nAddress: {$ethAddress}\nNonce: {$nonce}");
        }

        foreach ($messageCandidates as $message) {
            $isValid = $this->cosmosSignatureService->verifySignature(
                $message,
                $signature,
                $pubKey,
                $kiAddress
            );

            if ($isValid) {
                return ['valid' => true, 'reason' => 'Signature verified'];
            }
        }

        return ['valid' => false, 'reason' => 'Signature verification failed — no matching message variant'];
    }

    /**
     * Validate all pending claims in batch.
     *
     * @return array{approved: int, rejected: int, skipped: int, details: array}
     */
    public function validateAllPending(): array
    {
        $pendingClaims = $this->claimRepository->findByStatus('pending');

        $approved = 0;
        $rejected = 0;
        $skipped = 0;
        $details = [];

        foreach ($pendingClaims as $claim) {
            $result = $this->validateClaim($claim);

            if ($result['valid']) {
                $claim->setStatus('approved');
                $claim->setAdminNotes(
                    trim(($claim->getAdminNotes() ?? '') . "\n[Auto] Signature verified — " . date('Y-m-d H:i:s'))
                );
                $this->claimRepository->save($claim, false);
                $approved++;
            } else {
                $claim->setStatus('rejected');
                $claim->setAdminNotes(
                    trim(($claim->getAdminNotes() ?? '') . "\n[Auto] Rejected: {$result['reason']} — " . date('Y-m-d H:i:s'))
                );
                $this->claimRepository->save($claim, false);
                $rejected++;
            }

            $details[] = [
                'id' => $claim->getId(),
                'kiAddress' => $claim->getKiAddress(),
                'valid' => $result['valid'],
                'reason' => $result['reason'],
            ];
        }

        // Flush all at once
        $this->claimRepository->getEntityManager()->flush();

        return [
            'approved' => $approved,
            'rejected' => $rejected,
            'skipped' => $skipped,
            'total' => count($pendingClaims),
            'details' => $details,
        ];
    }
}
