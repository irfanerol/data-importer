<?php
/*
 * PseudoTransactionProcessor.php
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

namespace App\Services\CSV\Conversion\Routine;

use App\Exceptions\ImporterErrorException;
use App\Services\CSV\Conversion\Task\AbstractTask;
use App\Services\Shared\Conversion\ProgressInformation;
use App\Support\Token;
use GrumpyDictator\FFIIIApiSupport\Exceptions\ApiHttpException;
use GrumpyDictator\FFIIIApiSupport\Model\Account;
use GrumpyDictator\FFIIIApiSupport\Model\TransactionCurrency;
use GrumpyDictator\FFIIIApiSupport\Request\GetAccountRequest;
use GrumpyDictator\FFIIIApiSupport\Request\GetCurrencyRequest;
use GrumpyDictator\FFIIIApiSupport\Request\GetPreferenceRequest;
use GrumpyDictator\FFIIIApiSupport\Response\GetAccountResponse;
use GrumpyDictator\FFIIIApiSupport\Response\GetCurrencyResponse;
use GrumpyDictator\FFIIIApiSupport\Response\PreferenceResponse;
use Log;

/**
 * Class PseudoTransactionProcessor
 */
class PseudoTransactionProcessor
{
    use ProgressInformation;

    private array               $tasks;
    private Account             $defaultAccount;
    private TransactionCurrency $defaultCurrency;

    /**
     * PseudoTransactionProcessor constructor.
     *
     * @param int|null $defaultAccountId
     *
     * @throws ImporterErrorException
     */
    public function __construct(?int $defaultAccountId)
    {
        $this->tasks = config('csv.transaction_tasks');
        $this->getDefaultAccount($defaultAccountId);
        $this->getDefaultCurrency();
    }

    /**
     * @param int|null $accountId
     *
     * @throws ImporterErrorException
     */
    private function getDefaultAccount(?int $accountId): void
    {
        $url   = Token::getURL();
        $token = Token::getAccessToken();

        if (null !== $accountId) {
            $accountRequest = new GetAccountRequest($url, $token);
            $accountRequest->setVerify(config('importer.connection.verify'));
            $accountRequest->setTimeOut(config('importer.connection.timeout'));
            $accountRequest->setId($accountId);
            /** @var GetAccountResponse $result */
            try {
                $result = $accountRequest->get();
            } catch (ApiHttpException $e) {
                Log::error($e->getMessage());
                throw new ImporterErrorException(sprintf('The default account in your configuration file (%d) does not exist.', $accountId));
            }
            $this->defaultAccount = $result->getAccount();
        }
    }

    /**
     * @throws ImporterErrorException
     */
    private function getDefaultCurrency(): void
    {
        $url   = Token::getURL();
        $token = Token::getAccessToken();

        $prefRequest = new GetPreferenceRequest($url, $token);
        $prefRequest->setVerify(config('importer.connection.verify'));
        $prefRequest->setTimeOut(config('importer.connection.timeout'));
        $prefRequest->setName('currencyPreference');

        try {
            /** @var PreferenceResponse $response */
            $response = $prefRequest->get();
        } catch (ApiHttpException $e) {
            Log::error($e->getMessage());
            throw new ImporterErrorException('Could not load the users currency preference.');
        }
        $code            = $response->getPreference()->data ?? 'EUR';
        $currencyRequest = new GetCurrencyRequest($url, $token);
        $currencyRequest->setVerify(config('importer.connection.verify'));
        $currencyRequest->setTimeOut(config('importer.connection.timeout'));
        $currencyRequest->setCode($code);
        try {
            /** @var GetCurrencyResponse $result */
            $result                = $currencyRequest->get();
            $this->defaultCurrency = $result->getCurrency();
        } catch (ApiHttpException $e) {
            Log::error($e->getMessage());
            throw new ImporterErrorException(sprintf('The default currency ("%s") could not be loaded.', $code));
        }
    }

    /**
     * @param array $lines
     *
     * @return array
     */
    public function processPseudo(array $lines): array
    {
        Log::debug(sprintf('Now in %s', __METHOD__));
        $count     = count($lines);
        $processed = [];
        Log::info(sprintf('Converting %d lines into transactions.', $count));
        /** @var array $line */
        foreach ($lines as $index => $line) {
            Log::info(sprintf('Now processing line %d/%d.', ($index + 1), $count));
            $processed[] = $this->processPseudoLine($line);
        }
        Log::info(sprintf('Done converting %d lines into transactions.', $count));

        return $processed;

    }

    /**
     * @param array $line
     *
     * @return array
     */
    private function processPseudoLine(array $line): array
    {
        Log::debug(sprintf('Now in %s', __METHOD__));
        foreach ($this->tasks as $task) {
            /** @var AbstractTask $object */
            $object = app($task);
            Log::debug(sprintf('Now running task %s', $task));

            if ($object->requiresDefaultAccount()) {
                $object->setAccount($this->defaultAccount);
            }
            if ($object->requiresTransactionCurrency()) {
                $object->setTransactionCurrency($this->defaultCurrency);
            }

            $line = $object->process($line);
        }

        return $line;
    }

}
