<?php
/**
 * Created by PhpStorm.
 * User: bogdans
 * Date: 7/15/17
 * Time: 4:00 PM
 */

namespace JustFuse\ClickhouseClient\Client;


use JustFuse\ClickhouseClient\Client\Format\FormatInterface;
use JustFuse\ClickhouseClient\Connector\Connector;
use JustFuse\ClickhouseClient\Connector\Request;

class Client
{
    /** @var  Config */
    private $config;

    /** @var  Connector */
    private $connector;

    /** @var  FormatInterface */
    private $format;

    /**
     * Client constructor.
     * @param Config $config
     */
    public function __construct(Config $config, string $defaultFormatClass)
    {
        $this->config = $config;

        $connectorConfig = new \JustFuse\ClickhouseClient\Connector\Config();
        $connectorConfig->setHost($this->config->getBasics()['host']);
        $connectorConfig->setPort($this->config->getBasics()['port']);
        $connectorConfig->setProtocol($this->config->getBasics()['protocol']);
        $connectorConfig->setUser($this->config->getCredentials()['user']);
        $connectorConfig->setPassword($this->config->getCredentials()['password']);

        $this->connector = new Connector($connectorConfig);

        $this->validateFormatClass($defaultFormatClass);

        $this->format = new $defaultFormatClass();
    }

    private function validateFormatClass(string $formatClass)
    {
        if (!in_array(FormatInterface::class, class_implements($formatClass))) {
            throw new \Exception('Default format class received (' . $formatClass . ') does not implement ' . FormatInterface::class);
        }
    }

    /**
     * @param string $sql
     * @param string|null $formatClass
     * @return \JustFuse\ClickhouseClient\Connector\Response
     */
    public function query(string $sql, string $formatClass = null)
    {
        // set default format
        $format = $this->format;

        // check if custom format received
        if ($formatClass) {
            $this->validateFormatClass($formatClass);
            $format = new $formatClass;
        }

        // add format to SQL
        $sql = rtrim(trim($sql), ';');
        $sql .= ' FORMAT ' . $format->format(false);

        // make a request
        $request = new Request();
        $request->setGet(
            array_merge(
                $this->config->getSettings(),
                ['query' => $sql],
                ['readonly' => 1]
            )
        );

        // return response
        return $this->connector->perform($request, $format);
    }

    public function writePlain(string $sql)
    {
        $request = new Request();
        $request->setGet($this->config->getSettings());
        $request->setPostRaw($sql);

        return $this->connector->perform($request);
    }

    public function writeRows(string $sql, array $rows, string $formatClass = null)
    {
        // set default format
        $format = $this->format;

        // check if custom format received
        if ($formatClass) {
            $this->validateFormatClass($formatClass);
            $format = new $formatClass;
        }

        // add format to SQL
        $sql = rtrim(trim($sql), ';');
        $sql .= ' FORMAT ' . $format->format(true);

        $request = new Request();
        $request->setGet(
            array_merge(
                $this->config->getSettings(),
                ['query' => $sql]
            )
        );
        $rowsEncoded = '';
        foreach($rows as $row) {
            $rowsEncoded .= $this->format->encode($row) . PHP_EOL;
        }
        $request->setPostRaw($rowsEncoded);

        return $this->connector->perform($request);
    }

    public function writeStream(string $sql, $resource, string $formatClass = null)
    {
        // set default format
        $format = $this->format;

        // check if custom format received
        if ($formatClass) {
            $this->validateFormatClass($formatClass);
            $format = new $formatClass;
        }

        // add format to SQL
        $sql = rtrim(trim($sql), ';');
        $sql .= ' FORMAT ' . $format->format(true);

        $request = new Request();
        $request->setGet(
            array_merge(
                $this->config->getSettings(),
                ['query' => $sql]
            )
        );
        $request->setPostStream($resource);

        return $this->connector->perform($request);
    }

    /**
     * @param string $sql
     * @return \JustFuse\ClickhouseClient\Connector\Response
     */
    public function system(string $sql)
    {
        $request = new Request();
        $request->setGet($this->config->getSettings());
        $request->setPostRaw($sql);

        return $this->connector->perform($request);
    }
}