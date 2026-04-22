#!/usr/bin/env php
<?php

/**
 * Merge Tool (Token-based)
 *
 * Takes an entry point PHP file and merges all require_once dependencies into a single output file.
 * Usage: php tools/merge.php <entry_point> <output_file>
 */

if ($argc < 3) {
    fprintf(STDERR, "Usage: php %s <entry_point> <output_file>\n", $argv[0]);
    exit(1);
}

$entryPoint = $argv[1];
$outputFile = $argv[2];

if (!file_exists($entryPoint)) {
    fprintf(STDERR, "Error: Entry point file '%s' not found.\n", $entryPoint);
    exit(1);
}

$processedFiles = [];

/**
 * Recursively processes a file and its dependencies using PHP tokens.
 *
 * @param string $filePath The path to the file to process.
 * @param bool $isEntryPoint Whether this is the initial file.
 * @return string The processed content.
 */
function process_file($filePath, $isEntryPoint = false) {
    global $processedFiles;

    $realPath = realpath($filePath);
    if (!$realPath) {
        return "/* Error: Could not resolve path $filePath */\n";
    }

    if (isset($processedFiles[$realPath])) {
        return "/* require_once skipped: $filePath already included */\n";
    }
    $processedFiles[$realPath] = true;

    $source = file_get_contents($realPath);
    $tokens = token_get_all($source);
    $currentDir = dirname($realPath);
    $output = '';

    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (is_array($token)) {
            $id = $token[0];
            $text = $token[1];

            // Strip opening tag for non-entry files
            if ($id === T_OPEN_TAG && !$isEntryPoint) {
                continue;
            }
            // Strip closing tag for all files to allow concatenation
            if ($id === T_CLOSE_TAG) {
                continue;
            }

            // Look for require_once
            if ($id === T_REQUIRE_ONCE) {
                $j = $i + 1;
                $foundPath = '';
                $bracketLevel = 0;

                // Move forward to find the path string, handling potential __DIR__ or dirname(__FILE__)
                while ($j < $count) {
                    $t = $tokens[$j];
                    if (is_string($t)) {
                        if ($t === '(') $bracketLevel++;
                        if ($t === ')') $bracketLevel--;
                        if ($t === ';' && $bracketLevel === 0) break;
                    } elseif (is_array($t)) {
                        if ($t[0] === T_CONSTANT_ENCAPSED_STRING) {
                            $foundPath .= trim($t[1], "\"'");
                        }
                    }
                    $j++;
                }

                if ($foundPath !== '') {
                    $fullPath = $currentDir . DIRECTORY_SEPARATOR . $foundPath;
                    $output .= "\n/* --- Start of $foundPath --- */\n";
                    $output .= rtrim(process_file($fullPath, false));
                    $output .= "\n/* --- End of $foundPath --- */\n";
                    $i = $j; // Advance main loop to the semicolon
                    continue;
                }
            }

            $output .= $text;
        } else {
            $output .= $token;
        }
    }

    return $output;
}

// Start processing
$finalContent = process_file($entryPoint, true);

// Ensure output directory exists
$outputDir = dirname($outputFile);
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

// Write the result
if (file_put_contents($outputFile, $finalContent) === false) {
    fprintf(STDERR, "Error: Failed to write to %s\n", $outputFile);
    exit(1);
}

echo "Successfully merged $entryPoint and its dependencies into $outputFile\n";
