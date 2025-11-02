<?php

declare(strict_types=1);

namespace Four\Elo\Command;

use Exception;
use Four\Elo\Service\DatabaseReader;
use Four\Elo\Service\ExportOrganizer;
use Four\Elo\Service\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ExportCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('export')
            ->setDescription('Export ELO DMS archive to Nextcloud-ready folder structure')
            ->addArgument(
                'database',
                InputArgument::REQUIRED,
                'Path to ELO MDB database file'
            )
            ->addArgument(
                'files',
                InputArgument::REQUIRED,
                'Path to ELO files directory'
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Output directory for export',
                './nextcloud-export'
            )
            ->addOption(
                'ids',
                null,
                InputOption::VALUE_REQUIRED,
                'Comma-separated list of object IDs to export (useful for re-exporting specific documents)'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('ELO DMS Export Tool');

        $databasePath = $input->getArgument('database');
        $filesPath = $input->getArgument('files') ?? 'Archivdata';
        $outputPath = $input->getOption('output') ?? './Export';

        // Validate inputs
        if (!file_exists($databasePath)) {
            $io->error("Database file not found: $databasePath");
            return Command::FAILURE;
        }

        if (!is_dir($filesPath)) {
            $io->error("Files directory not found: $filesPath");
            return Command::FAILURE;
        }

        $io->section('Configuration');
        $io->listing([
            "Database: $databasePath",
            "Files: $filesPath",
            "Output: $outputPath",
        ]);

        try {
            // Initialize logger (write to project var/log directory)
            $projectRoot = dirname(__DIR__, 2);
            $logPath = $projectRoot . '/var/log';
            $logger = Logger::createWithLogFile($logPath);
            $logger->info('=== ELO DMS Export Started ===');
            $logger->info('Database: ' . $databasePath);
            $logger->info('Files Path: ' . $filesPath);
            $logger->info('Output Path: ' . $outputPath);
            $logger->info('Log Path: ' . $logPath);

            // Initialize services
            $io->section('Initializing services...');
            $dbReader = new DatabaseReader($databasePath);
            $organizer = new ExportOrganizer($outputPath);
            $logger->info('Services initialized successfully');

            // Read database (get count first for progress bar)
            $io->section('Reading ELO database...');

            // Check if specific IDs are requested
            $idsOption = $input->getOption('ids');
            if ($idsOption) {
                // Parse comma-separated IDs
                $ids = array_map('intval', array_filter(array_map('trim', explode(',', $idsOption))));
                $documents = $dbReader->getDocumentsById($ids);
                $documentCount = count($documents);
                $io->success(sprintf('Found %d documents matching IDs: %s', $documentCount, implode(', ', $ids)));
                $logger->info('Exporting ' . $documentCount . ' documents by IDs: ' . implode(', ', $ids));
            } else {
                $documentCount = $dbReader->getDocumentCount();
                $documents = $dbReader->getDocuments();
                $io->success(sprintf('Found %d documents in database', $documentCount));
                $logger->info('Found ' . $documentCount . ' documents in database');
            }

            // Initialize output directory
            $organizer->initialize();

            // Check for already exported IDs (auto-loads on first access)
            $alreadyExported = $organizer->getExportedCount();
            if ($alreadyExported > 0) {
                $io->note(sprintf('Found %d already exported documents (will skip)', $alreadyExported));
                $logger->info('Found ' . $alreadyExported . ' already exported documents');
            }

            // Process documents
            $io->section('Processing documents...');
            $io->progressStart($documentCount);

            $processed = 0;
            $skipped = 0;
            $errors = [];
            foreach ($documents as $document) {
                try {
                    // Check if already exported (auto-resume)
                    if ($organizer->isExported($document->objid) || $idsOption) {
                        $skipped++;
                        $logger->debug('Skipped already exported document without objdoc', [
                            'objid' => $document->objid ?? 'unknown',
                            'objshort' => $document->objshort ?? 'unknown'
                        ]);
                        $io->progressAdvance();
                        continue;
                    }

                    // Get objdoc (required)
                    if (!($document->objdoc ?? null)) {
                        $skipped++;
                        $logger->debug('Skipped document without objdoc', [
                            'objid' => $document->objid ?? 'unknown',
                            'objshort' => $document->objshort ?? 'unknown'
                        ]);
                        $io->progressAdvance();
                        continue;
                    }

                    // Build paths early for logging
                    $sourceBaseFile = $dbReader->buildFilePath($document, $filesPath);
                    $relativePath =  $dbReader->createDocumentPath($document);

                    // Build file path pattern without extension (objdoc as hex filename)
                    $filePathPattern =  "$sourceBaseFile.*";

                    // Glob for file with any extension
                    $possibleFiles = glob($filePathPattern, GLOB_NOSORT);

                    if (empty($possibleFiles)) {
                        $error = sprintf('File not found for objdoc: %s (Pattern: %s, objid: %s)',
                            $document->objdoc ?? 'unknown',
                            $filePathPattern,
                            $document->objid ?? 'unknown'
                        );
                        $errors[] = $error;
                        $logger->warning($error);
                        $io->progressAdvance();
                        continue;
                    }

                    // Use first matching file
                    $sourcePath = $possibleFiles[0];

                    // Add to export with proper organization
                    $targetPath = $organizer->addFile($sourcePath, $relativePath);

                    // Mark as exported (auto-saves to exported_ids.json)
                    $organizer->markExported($document->objid);

                    // Log success
                    $logger->info('Processed document', [
                        'objid' => $document->objid ?? 'unknown',
                        'source' => $sourcePath,
                        'target' => $relativePath,
                    ]);

                    $processed++;
                } catch (Exception $e) {
                    $error = sprintf(
                        'Document %s (objid: %s): %s',
                        $document->objshort ?? 'unknown',
                        $document->objid ?? 'unknown',
                        $e->getMessage()
                    );
                    $errors[] = $error;
                    $logger->error($error, [
                        'source' => $sourceBaseFile ?? '',
                        'target' => $relativePath ?? '',
                        'exception' => $e
                    ]);
                }

                $io->progressAdvance();
            }

            $io->progressFinish();

            // Summary
            $io->section('Export Summary');
            $io->success(sprintf('Successfully processed %d documents', $processed));

            if ($skipped > 0) {
                $io->info(sprintf('Skipped %d documents', $skipped));
            }

            if (!empty($errors)) {
                $io->warning(sprintf('%d errors occurred:', count($errors)));
                $io->listing(array_slice($errors, 0, 10));
                if (count($errors) > 10) {
                    $io->note(sprintf('... and %d more errors', count($errors) - 10));
                }
            }

            $logger->info('=== Export Summary ===');
            $logger->info("Total documents: " . $documentCount);
            $logger->info("Processed: {$processed}");
            $logger->info("Skipped: {$skipped}");
            $logger->info("Errors: " . count($errors));
            $logger->info("Log file: " . $logger->getLogFile());


            $io->success("Export completed! Output: $outputPath");
            $io->info("Log file: " . $logger->getLogFile());

            return Command::SUCCESS;

        } catch (Exception $e) {
            if (isset($logger)) {
                $logger->error('Export failed: ' . $e->getMessage());
                $logger->error('Stack trace: ' . $e->getTraceAsString());
            }

            $io->error('Export failed: ' . $e->getMessage());
            if ($output->isVerbose()) {
                $io->block($e->getTraceAsString(), null, 'fg=red');
            }

            if (isset($logger)) {
                $io->note("Log file: " . $logger->getLogFile());
            }

            return Command::FAILURE;
        }
    }
}
