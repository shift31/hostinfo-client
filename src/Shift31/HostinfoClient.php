<?php

namespace Shift31;

/**
 * Class HostinfoClient
 *
 * @package Shift31
 */
class HostinfoClient
{
    private $_hostinfoUrl;

	protected $logger;

    private $_batchQueries = array();


    /**
     * @param string    $baseUrl    The base URL endpoint for the hostinfo web service  (i.e. http://hostinfo.example.com/hostinfo)
     * @param null      $logger     An optional instance of a logger class such as Monolog or Zend_Log
     */
    public function __construct($baseUrl, $logger = null)
    {
        $this->_hostinfoUrl = $baseUrl . '/csv';

		$this->_logger = $logger;
    }
    
    
    /**
     * @param $exprs
     * @return array $hosts
     * @throws \Exception
     */
    public function search($exprs)
    {
        if (!is_array($exprs)) {
            throw new \Exception('$exprs must be an array where each element contains a valid hostinfo expression in "alternative form"');
        }

        $hostinfoUrl = $this->_hostinfoUrl;

        // expressions are always ANDed together, hostinfo does not currently support ORing expressions
        foreach($exprs as $expr) {
            $hostinfoUrl .= "/$expr"; 
        }
        
        $hosts = array();
        
        // read entire file into an array
        try {
            $this->_log('debug', "Hostinfo URL: $hostinfoUrl");
            
            $lines = file($hostinfoUrl);
            
            //$this->_log('debug', print_r('$lines = ' . $lines, true));

            if (is_array($lines)) {

                $header = array();

                if (preg_match('/hostname/', $lines[0])) {
                    $header = str_getcsv($lines[0]);
                }

                foreach ($lines as $line) {
                    if (preg_match('/hostname/', $line)) continue;

                    $csv = str_getcsv($line);

                    for($i = 0; $i < count($csv); $i++) {
                        $hosts[$csv[0]][$header[$i]] = $csv[$i]; 
                    }
                }    
            }
        } catch (\Exception $e) {
            $this->_log('crit', 'Exception: ' . $e->getCode() . ' - ' . $e->getMessage());
        }
        
        //$this->_log('debug', '$hosts = ' . print_r($hosts, true));
        
        return $hosts;
    }


    /**
     * @return array $servers
     * @throws \Exception
     */
    public function batchSearch()
    {
        $queries = $this->_batchQueries;

        if (!is_array($queries)) {
            throw new \Exception('$queries must be an array of arrays where each element contains a valid hostinfo expression in "alternative form"');
        }

        /*
        $queries = array(
            array(
                'expr1',
                'expr2',
            ),
            array(
                'expr1',
                'expr2',
            ),
        );
        */
        
        $servers = array();

        $results = array();

        foreach ($queries as $exprs) {
            $results[] = $this->search($exprs);
        }

        foreach($results as $hosts) {
            foreach ($hosts as $host) {
                $servers[] = $host;
            }   
        }

        return $servers;
    }

    
    /**
     * @param string    $stringOfQueries    a comma-separated list of hostinfo queries containing pipe-separated (|) expressions
     * @return HostinfoClient
     */
    public function setBatchQueries($stringOfQueries)
    {
        // remove whitespace and line endings, and any trailing commas
        $stringOfQueries = rtrim(preg_replace("/\s+|\r|\n|\r\n/", '', $stringOfQueries), ',');

        $this->_log('debug', "Modified Hostinfo batch query string: $stringOfQueries");

        $queries = explode(',', $stringOfQueries);

        $batch = array();

        foreach ($queries as $exprs) {
            $batch[] = explode('|', $exprs);
        }
        
        $this->_batchQueries = $batch;

        return $this;
    }


	/**
	 *
	 * @param string $priority
	 * @param string $message
	 */
	protected function _log($priority, $message)
	{
		if ($this->_logger != null) {
			$class = str_replace(__NAMESPACE__ . "\\", '', get_called_class());
			$this->_logger->$priority("[$class] - $message");
		}
	}
}