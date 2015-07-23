<?php

/**
 * ActiveHighstockWidget class file.
 *
 * @author David Baker <github@acorncomputersolutions.com>
 * @link https://github.com/miloschuman/yii-highcharts/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 * @version 3.0.10
 */

require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'HighstockWidget.php');

/**
 * Usage:
 *
$this->widget('highcharts.ActiveHighstockWidget', array(
    'options' => array(
        'title' => array('text' => 'Site Percentile'),
        'yAxis' => array(
            'title' => array('text' => 'Site Rank')
        ),
        'series' => array(
            array(
                'name'  => 'Site percentile',
                'data'  => 'SiteRank12',        // data column in the dataprovider
                'time'  => 'RankDate',          // time column in the dataprovider
                // 'timeType'  => 'date',
                // defaults to a mysql timestamp, other options are 'date' (run through strtotime()) or 'plain'
            ),
            array(
                'name'  => 'Site percentile',
                'time'  => 'RankDate',          // time column in the dataprovider
                'type'  => 'arearange',
                'data'  => array(
                    'Column1',      // specify an array of data options
                    'Column2',      // if you are using an area range charts
                ),
                // to eliminate null values from a graph, use 'connectNulls' => false for Highstock
            ),
        ),
    ),
    'dataProvider' => $dataProvider,
));
 *
 * @see HighchartsWidget for additional options
 */
class ActiveHighstockWidget extends HighstockWidget
{
    /**
     * Pass in a data provider that we will turn into the series
     * @var CDataProvider
     */
    public $dataProvider;

    public function run()
    {
        $data = $this->dataProvider->getData();
        $series = $this->options['series'];

        if(count($data) > 0) {
            foreach ($series as $i => $batch) {

                // if this is true, we'll add additional data points right in to our series results
                $hashDataPoints = (isset($batch['hashDataPoints'])) ? $batch['hashDataPoints'] : false;

                if (isset($batch['time']) && isset($batch['data']) &&
                    !is_array($batch['time'])
                ) {
                    $dateSeries = array();
                    foreach ($data as $row) {
                        $dateSeries[] = $this->processRow($row, $batch, $hashDataPoints);
                    }

                    // we'll work on the actual item, this may be PHP 5.3+ specific
                    if($hashDataPoints) {
                        $this->sortDateSeriesByHash($dateSeries);
                    } else {
                        $this->sortDateSeriesSimpleArray($dateSeries);
                    }

                    // clean up our time item so we don't accidentally conflict with Highstock
                    unset($this->options['series'][$i]['time']);

                    // and then reset our data column with our data series
                    $this->options['series'][$i]['data'] = $dateSeries;
                }
            }
        }

        parent::run();
    }

    /**
     * Handles processing a row and readying it for Highstock
     *
     * @param $row
     * @param $batch
     * @param $hashDataPoints
     *
     * @return array
     */
    protected function processRow($row, $batch, $hashDataPoints) {
        // if we're dealing with a javascript timestamp
        // then just setup our array
        $timeType = (isset($batch['timeType'])) ? $batch['timeType'] : 'mysql';

        switch ($timeType) {
            case 'plain':
                $time = $this->processPlainTimestamp($row, $batch);
                break;
            case 'date':
                $time = $this->processDateString($row, $batch);
                break;
            case 'mysql':
                $time = $this->processMysqlTimestamp($row, $batch);
                break;
            default:
                $functionName = 'process' . ucfirst($timeType);
                if(method_exists($this, $functionName)) {
                    return call_user_func(array($this, $functionName), $row, $batch);
                } else {
                    throw new Exception("Can't call your custom date processing function");
                }
        }

        // process our data by running it through our data processing method
        // and then place the time value on the front
        $data = $this->processData($row, $batch, $time, $hashDataPoints);

        return $data;
    }

    /**
     * Cleans up the data column so Highstock is happy
     *
     * @param $row
     * @param $batch
     * @param $time
     * @param $hashDataPoints
     *
     * @return array
     */
    protected function processData($row, $batch, $time, $hashDataPoints)
    {
        if(!is_array($batch['data'])) {
            $value = (!isset($row[$batch['data']]) || is_null($row[$batch['data']])) ? null : floatval($row[$batch['data']]);
            return array($time, $value);
        }

        $items = array();
        foreach($batch['data'] as $key => $item) {

            if($hashDataPoints) {
                $items[$key] = is_null($row[$item]) ? null : floatval($row[$item]);
                $numericKeys = false;
            } else {
                $items[] = is_null($row[$item]) ? null : floatval($row[$item]);
            }
        }

        // now we handle properly handling our time value
        if($hashDataPoints) {
            $items['x'] = $time;
        } else {
            array_unshift($items, $time);
        }

        return $items;
    }

    /**
     * Using this means your time needs to be in JS milliseconds
     *
     * @param $row
     * @param $batch
     * @return array
     */
    protected function processPlainTimestamp($row, $batch) {
        return floatval($row[$batch['time']]);
    }

    /**
     * Converts dates using strtotime() to a MySQL timestamp and then changes to JS milliseconds
     *
     * @param $row
     * @param $batch
     * @return array
     */
    protected function processDateString($row, $batch) {
        return 1000 * floatval(strtotime($row[$batch['time']]));
    }

    /**
     * Converts a SQL unix timestamp to a JS timestamp (in milliseconds)
     * This is our default time processor if not specified
     *
     * @param $row
     * @param $batch
     * @return array
     */
    protected function processMysqlTimestamp($row, $batch) {
        return 1000 * floatval($row[$batch['time']]);
    }

    /**
     * Sorts our date series so we have all the dates from first to last
     * @param $series
     */
    protected function sortDateSeriesSimpleArray(&$series) {

        //sort by first column (dates ascending order)
        foreach ($series as $key => $row) {
            $dates[$key] = $row[0];
        }
        array_multisort($dates, SORT_ASC, $series);
    }
    /**
     * Sorts our date series so we have all the dates from first to last
     * @param $series
     */
    protected function sortDateSeriesByHash(&$series) {

        //sort by first column (dates ascending order)
        foreach ($series as $key => $row) {
            $dates[$key] = $row['x'];
        }
        array_multisort($dates, SORT_ASC, $series);
    }
}