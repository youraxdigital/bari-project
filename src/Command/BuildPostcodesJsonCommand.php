<?php

namespace App\Command;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:postcodes:build-json', description: 'Build per-city JSON files from an Excel template')]
class BuildPostcodesJsonCommand extends Command
{
    protected function configure(): void
    {
        // php bin/console app:postcodes:build-json public/uploads/Template.xlsx public/data/postcodes
        $this
            ->addArgument('xlsx', InputArgument::REQUIRED, 'Path to Excel (e.g. public/uploads/Template.xlsx)')
            ->addArgument('outdir', InputArgument::OPTIONAL, 'Output dir', 'public/data/postcodes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $xlsx    = $input->getArgument('xlsx');
        $outdir  = rtrim($input->getArgument('outdir'), '/');

        if (!is_file($xlsx)) {
            $io->error("Excel not found: $xlsx");
            return Command::FAILURE;
        }
        if (!is_dir($outdir) && !mkdir($outdir, 0775, true)) {
            $io->error("Cannot create output dir: $outdir");
            return Command::FAILURE;
        }

        $spreadsheet = IOFactory::load($xlsx);
        $sheet = $spreadsheet->getActiveSheet();
        $rows  = $sheet->toArray(null, true, true, true);

        // Attendu: en-têtes Ville | Quartier | CODE postal
        // Ligne 1 = headers
        $byCity = [];
        for ($i = 2; $i <= count($rows); $i++) {
            $ville    = strtoupper(trim((string)($rows[$i]['A'] ?? '')));
            $quartier = trim((string)($rows[$i]['B'] ?? ''));
            $cp       = trim((string)($rows[$i]['C'] ?? ''));

            if ($ville === '' || $cp === '') { continue; }

            $byCity[$ville] ??= [];
            $byCity[$ville][] = ['quartier' => $quartier, 'cp' => $cp];
        }

        foreach ($byCity as $city => $list) {
            $file = sprintf('%s/%s.json', $outdir, $city);
            file_put_contents($file, json_encode($list, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
            $io->success("Wrote $file (".count($list)." rows)");
        }

        return Command::SUCCESS;
    }
}
