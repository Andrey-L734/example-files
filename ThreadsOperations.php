<?php

namespace App\Models;

use App\Core\Auth\Auth;
use App\Core\Config\Config;

class ThreadsOperations extends Threads
{
    private string $typeOperation;
    private bool $isHasUnknownThreads = false;

    public function __construct()
    {
        parent::__construct();
        $this->table = 'operations';
        $this->column = ['user_id', 'type', 'threads_list'];
    }

    /**
     * Collects an array of parameters to be passed to a prepared request
     * @return void
     */
    public function makeParamsFromStoreRequest(): void
    {
        $this->paramsFromStoreRequest['user_id'] = Auth::$user->getId();
        $this->paramsFromStoreRequest['type'] = $this->typeOperation;
        $this->paramsFromStoreRequest['threads_list'] = json_encode($this->threads);
    }

    /**
     * @return void
     */
    public function setThreadsFromPost(): void
    {
        $post = $_POST;
        $factor = Config::get('model.metersPerSkein');
        $brand = 'brand';
        $measure = 'measure';
        $number = 'number';
        $value = 'value';
        $linkBrandArray = [];
        $resultArray = [];

        foreach ($post as $key => $item) {

            if (str_starts_with($key, $brand)) {
                preg_match('/\d/', $key, $found);
                $linkBrandArray[$found[0]] = $item;
                $tempResultArrayItem = $item;
            }

            if (str_starts_with($key, $number)) {
                $item = str_replace([' '],"",trim(strip_tags($item)));

                if (preg_match('/[a-zA-Z0-9]+/', $item) && !preg_match('/[^a-zA-Z0-9]+/', $item)) {
                    preg_match('/B\K\d/', $key, $foundBrand);
                    $tempBrandKey = $foundBrand[0];
                    $tempNumberItem = $item;
                } else {
                    $tempNumberItem = false;
                    continue;
                }
            }

            if (str_starts_with($key, $value) && $tempNumberItem) {
                $item = str_replace([' '],"",trim(strip_tags($item)));

                if (preg_match('/\d/', $item) && !preg_match('/\D/', $item)) {

                    if (empty($resultArray[$tempResultArrayItem])) {
                        $resultArray[$tempResultArrayItem] = [];
                    }

                    if ($post[$measure . '-' . $tempBrandKey] === 'skeins') {
                        $resultArray[$linkBrandArray[$foundBrand[0]]][$tempNumberItem] = (int)$item * $factor;
                    } else {
                        $resultArray[$linkBrandArray[$foundBrand[0]]][$tempNumberItem] = (int)$item;
                    }
                }
            }
        }

        $this->threads = $resultArray;
    }

    /**
     * Cuts numbers from the array that are not in the database and returns the cut ones
     * @return array
     */
    public function cutUnknownNumbers(): array
    {
        $brandsList = $this->getBrandFromDb();
        $correctColorListFromDb = $this->getColorsFromDb($brandsList);
        $result = [];
        foreach ($this->threads as $brandId => &$numbersList) {
            if (!empty($correctColorListFromDb[$brandId])) {
                $unknownNumbers = array_diff_key($numbersList, $correctColorListFromDb[$brandId]);
                if (!empty($unknownNumbers)) {
                    $tempBrandName = $brandsList[$brandId]['name'];
                    $result[$tempBrandName] = $unknownNumbers;
                    $numbersList = array_diff_key($numbersList, $unknownNumbers);
                }
            } else {
                $result = $numbersList;
                $numbersList = [];
            }
        }
        unset($numbersList);

        $this->threads = array_filter($this->threads, function($el) {
            return !empty($el);
        });

        if (!empty($result)) {
            $this->isHasUnknownThreads = true;
        }

        return $result;
    }

    /**
     * @param string $typeOperation
     */
    public function setTypeOperation(string $typeOperation): void
    {
        $this->typeOperation = $typeOperation;
    }

    /**
     * @return string
     */
    public function getTypeOperation(): string
    {
        return $this->typeOperation;
    }

    /**
     * @return array
     */
    public function getIsHasUnknownThreads(): bool
    {
        return $this->isHasUnknownThreads;
    }

    /**
     * @param bool $isHasUnknownThreads
     */
    public function setIsHasUnknownThreads(bool $isHasUnknownThreads): void
    {
        $this->isHasUnknownThreads = $isHasUnknownThreads;
    }
}