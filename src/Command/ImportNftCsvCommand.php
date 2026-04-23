<?php

namespace App\Command;

use App\Entity\NftAsset;
use App\Entity\NftClaimConfig;
use App\Service\NftAllocationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-nft-csv',
    description: 'Import NFT metadata CSV into nft_asset table and calculate allocations'
)]
class ImportNftCsvCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private NftAllocationService $allocationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Path to CSV file')
            ->addOption('pool', null, InputOption::VALUE_OPTIONAL, 'Total pool size in $XKI', '5000000')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate without writing')
            ->addOption('clear', null, InputOption::VALUE_NONE, 'Clear existing nft_asset data before import');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $csvFile = $input->getArgument('file');
        $poolTotal = (float) $input->getOption('pool');
        $dryRun = $input->getOption('dry-run');
        $clear = $input->getOption('clear');

        if (!file_exists($csvFile)) {
            $io->error("CSV file not found: $csvFile");
            return Command::FAILURE;
        }

        $io->title('Import NFT Metadata CSV');
        $io->text("File: $csvFile");
        $io->text("Pool: " . number_format($poolTotal) . " \$XKI");
        if ($dryRun) $io->warning('DRY RUN — no data will be written');

        // 1. Parse CSV
        $handle = fopen($csvFile, 'r');
        $header = fgetcsv($handle);
        $io->text("CSV columns: " . implode(', ', $header));

        $rows = [];
        $scarcityCounts = [];
        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) < 6) continue;

            $collection = $data[0];
            // Only CosmonNFT (exclude JokyNFT)
            if (!str_starts_with($collection, 'CosmonNFT')) continue;

            $scarcity = $data[5];
            $scarcityCounts[$scarcity] = ($scarcityCounts[$scarcity] ?? 0) + 1;

            $rows[] = [
                'collection' => $data[0],
                'token_id' => $data[1],
                'owner' => $data[2],
                'name' => $data[3],
                'image' => $data[4],
                'scarcity' => $scarcity,
                'personality' => $data[6] ?? null,
                'geographical' => $data[7] ?? null,
                'time' => $data[8] ?? null,
                'nationality' => $data[9] ?? null,
                'asset_id' => $data[10] ?? null,
                'short_description' => $data[11] ?? null,
            ];
        }
        fclose($handle);

        $io->text("Parsed " . count($rows) . " rows");
        $io->table(
            ['Scarcity', 'Count'],
            array_map(fn($s, $c) => [$s, $c], array_keys($scarcityCounts), array_values($scarcityCounts))
        );

        // 2. Calculate allocations
        $allocations = $this->allocationService->calculateAllocations($scarcityCounts, $poolTotal);

        $io->section('Allocations per NFT');
        $io->table(
            ['Scarcity', 'Count', 'Per NFT ($XKI)', 'Subtotal'],
            array_map(fn($s) => [
                $s,
                $scarcityCounts[$s] ?? 0,
                number_format($allocations[$s], 2),
                number_format(($scarcityCounts[$s] ?? 0) * $allocations[$s], 2),
            ], array_keys($allocations))
        );

        $totalAllocated = 0;
        foreach ($allocations as $scarcity => $perNft) {
            $totalAllocated += ($scarcityCounts[$scarcity] ?? 0) * $perNft;
        }
        $io->text("Total allocated: " . number_format($totalAllocated, 2) . " / " . number_format($poolTotal, 2) . " \$XKI");

        if ($dryRun) {
            $io->success('DRY RUN complete. No data written.');
            return Command::SUCCESS;
        }

        // 3. Clear if requested
        if ($clear) {
            $this->em->createQuery('DELETE FROM App\Entity\NftAsset')->execute();
            $io->text('Cleared existing nft_asset data');
        }

        // 4. Batch insert
        $io->section('Importing...');
        $batchSize = 500;
        $imported = 0;

        foreach ($rows as $i => $row) {
            $asset = new NftAsset();
            $asset->setCollection($row['collection']);
            $asset->setTokenId($row['token_id']);
            $asset->setOwner($row['owner']);
            $asset->setName($row['name']);
            $asset->setImage($row['image']);
            $asset->setScarcity($row['scarcity']);
            $asset->setPersonality($row['personality']);
            $asset->setGeographical($row['geographical']);
            $asset->setTime($row['time']);
            $asset->setNationality($row['nationality']);
            $asset->setAssetId($row['asset_id']);
            $asset->setShortDescription($row['short_description']);
            $asset->setAllocation((string) $allocations[$row['scarcity']]);

            $this->em->persist($asset);
            $imported++;

            if (($i + 1) % $batchSize === 0) {
                $this->em->flush();
                $this->em->clear();
                $io->text("  Flushed $imported / " . count($rows));
            }
        }

        $this->em->flush();
        $this->em->clear();

        // 5. Seed config
        $configs = [
            'deadline' => '2026-07-01T00:00:00Z',
            'pool_total' => (string) $poolTotal,
            'enabled' => 'true',
        ];

        foreach ($configs as $key => $value) {
            $existing = $this->em->getRepository(NftClaimConfig::class)->find($key);
            if (!$existing) {
                $config = new NftClaimConfig();
                $config->setKey($key);
                $config->setValue($value);
                $this->em->persist($config);
            }
        }
        $this->em->flush();

        $uniqueOwners = $this->em->getRepository(NftAsset::class)->countUniqueOwners();
        $io->success("Imported $imported NFTs for $uniqueOwners unique wallets. Config seeded.");

        return Command::SUCCESS;
    }
}
