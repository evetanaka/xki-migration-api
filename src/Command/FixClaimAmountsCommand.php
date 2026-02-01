<?php

namespace App\Command;

use App\Repository\ClaimRepository;
use App\Repository\EligibilityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fix-claim-amounts',
    description: 'Fix claims with amount=0 by cross-referencing with eligibility table'
)]
class FixClaimAmountsCommand extends Command
{
    public function __construct(
        private ClaimRepository $claimRepository,
        private EligibilityRepository $eligibilityRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Find all claims with amount = 0
        $claims = $this->claimRepository->findBy(['amount' => 0]);

        if (count($claims) === 0) {
            $io->success('No claims with amount=0 found.');
            return Command::SUCCESS;
        }

        $io->info(sprintf('Found %d claims with amount=0', count($claims)));

        $fixed = 0;
        foreach ($claims as $claim) {
            $kiAddress = $claim->getKiAddress();
            
            // Find eligibility record
            $eligibility = $this->eligibilityRepository->findOneBy(['kiAddress' => $kiAddress]);
            
            if (!$eligibility) {
                $io->warning(sprintf('No eligibility found for %s', $kiAddress));
                continue;
            }

            $balance = (int) $eligibility->getBalance();
            
            if ($balance > 0) {
                $claim->setAmount($balance);
                $this->entityManager->persist($claim);
                $io->writeln(sprintf(
                    'Fixed: %s -> %s XKI',
                    $kiAddress,
                    number_format($balance / 1000000, 6)
                ));
                $fixed++;
            } else {
                $io->warning(sprintf('Zero balance for %s', $kiAddress));
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf('Fixed %d claims.', $fixed));

        return Command::SUCCESS;
    }
}
