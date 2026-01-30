<?php

namespace App\Command;

use App\Entity\Eligibility;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-snapshot',
    description: 'Import Ki Chain snapshot CSV into eligibility table'
)]
class ImportSnapshotCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('csv_file', InputArgument::REQUIRED, 'Path to the snapshot CSV file')
            ->addArgument('min_balance', InputArgument::OPTIONAL, 'Minimum balance in XKI to be eligible', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $csvFile = $input->getArgument('csv_file');
        $minBalance = (float) $input->getArgument('min_balance');

        if (!file_exists($csvFile)) {
            $io->error("CSV file not found: $csvFile");
            return Command::FAILURE;
        }

        $io->title('Importing Ki Chain Snapshot');
        $io->text("File: $csvFile");
        $io->text("Minimum balance: $minBalance XKI");

        $handle = fopen($csvFile, 'r');
        if (!$handle) {
            $io->error("Could not open CSV file");
            return Command::FAILURE;
        }

        // Skip header
        $header = fgetcsv($handle);
        $io->text("CSV columns: " . implode(', ', $header));

        $imported = 0;
        $skipped = 0;
        $batchSize = 500;

        $io->progressStart();

        while (($row = fgetcsv($handle)) !== false) {
            $address = $row[0];
            $balanceUxki = $row[1];
            $balanceXki = (float) $row[2];

            // Skip if balance is below minimum
            if ($balanceXki < $minBalance) {
                $skipped++;
                continue;
            }

            // Check if already exists
            $existing = $this->entityManager->getRepository(Eligibility::class)
                ->findOneBy(['kiAddress' => $address]);

            if ($existing) {
                // Update balance
                $existing->setBalance($balanceUxki);
                $existing->setEligible($balanceXki > 0);
            } else {
                // Create new
                $eligibility = new Eligibility();
                $eligibility->setKiAddress($address);
                $eligibility->setBalance($balanceUxki);
                $eligibility->setEligible($balanceXki > 0);
                $this->entityManager->persist($eligibility);
            }

            $imported++;

            // Flush in batches
            if ($imported % $batchSize === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
                $io->progressAdvance($batchSize);
            }
        }

        // Final flush
        $this->entityManager->flush();
        fclose($handle);

        $io->progressFinish();

        $io->success([
            "Import completed!",
            "Imported: $imported addresses",
            "Skipped (below min balance): $skipped addresses"
        ]);

        return Command::SUCCESS;
    }
}
