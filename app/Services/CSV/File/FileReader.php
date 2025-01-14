<?php
/*
 * FileReader.php
 * Copyright (c) 2021 james@firefly-iii.org
 *
 * This file is part of the Firefly III Data Importer
 * (https://github.com/firefly-iii/data-importer).
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Services\CSV\File;


use App\Services\Session\Constants;
use App\Services\Storage\StorageService;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use League\Csv\Reader;

/**
 * Class FileReader
 */
class FileReader
{
    /**
     * Get a CSV file reader and fill it with data from CSV file.
     *
     * @param bool $convert
     * @return Reader
     * @throws FileNotFoundException
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public static function getReaderFromSession(bool $convert = false): Reader
    {
        $content = StorageService::getContent(session()->get(Constants::UPLOAD_CSV_FILE), $convert);

        // room for config
        return Reader::createFromString($content);
    }

    /**
     * @param string $content
     *
     * @return Reader
     */
    public static function getReaderFromContent(string $content): Reader
    {
        return Reader::createFromString($content);
    }

}
