<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace tool_secure_s3_storage\local;

/**
 * Finds eligible archives without crossing the configured source boundary.
 *
 * @package   tool_secure_s3_storage
 * @copyright 2026 Hiroshi Ozeki
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class archive_scanner {
    /**
     * Returns regular, non-symlinked .mbz files directly below the source.
     *
     * @param configuration $configuration validated configuration
     * @return array<int, array{path: string, sourcehash: string, filename: string, size: int, mtime: int}>
     */
    public function scan(configuration $configuration): array {
        $archives = [];
        $prefix = $configuration->sourcedirectory . DIRECTORY_SEPARATOR;
        $iterator = new \FilesystemIterator(
            $configuration->sourcedirectory,
            \FilesystemIterator::SKIP_DOTS
        );

        foreach ($iterator as $entry) {
            if ($entry->isLink() || !$entry->isFile() || !str_ends_with($entry->getFilename(), '.mbz')) {
                continue;
            }
            if (preg_match('/[\\x00-\\x1f\\x7f]/', $entry->getFilename())) {
                continue;
            }

            $path = realpath($entry->getPathname());
            if ($path === false || !str_starts_with($path, $prefix) || !is_readable($path)) {
                continue;
            }

            $stat = stat($path);
            if ($stat === false || $stat['size'] < 1) {
                continue;
            }

            $archives[] = [
                'path' => $path,
                'sourcehash' => hash('sha256', $path),
                'filename' => $entry->getFilename(),
                'size' => (int)$stat['size'],
                'mtime' => (int)$stat['mtime'],
            ];
        }

        usort($archives, static fn(array $a, array $b): int => strcmp($a['filename'], $b['filename']));
        return $archives;
    }
}
