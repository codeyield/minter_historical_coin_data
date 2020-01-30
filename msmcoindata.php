<?php
/*
 * This script allows you to get historical coin data from the Minter blockchain, 
 * such as volume, reserve balance and price for the selected blocks (that is, at any specified time)
 * 
 * It uses a MSCAN.DEV API for full node. Request examples:
 * https://api.mscan.dev/YOURAPIKEY/full_node/coin_info?symbol=MISSMINTER&height=2942273
 * https://api.mscan.dev/YOURAPIKEY/full_node/estimate_coin_sell?coin_to_sell=MISSMINTER&coin_to_buy=BIP&value_to_sell=1000000000000000000&height=2942273
 *
 * It uses a free node API to get general block data. Request example:
 * https://api.minter.one/block?height=2942273
 *
 * How to use:
 * http://localhost/msmcoindata.php
 * http://localhost/msmcoindata.php?start=2942273
 * http://localhost/msmcoindata.php?start=2942273&step=720&count=100
 */

/*
 * You must set here:
 * 1. Your mscan.dev API key
 * 2. File name for writing CSV data
 * 3. Coin's ticker you are explore
 */
const MSCANDEV_APIKEY = 'YOUR_MSCANDEV_API_KEY';
const OUTPUT_FILE = 'data.txt';
const COIN_NAME = 'MISSMINTER';

/*
 * API URLs
 */
define('MSCAN_API_URL', 'https://api.mscan.dev/'.MSCANDEV_APIKEY.'/full_node/');
define('FREE_API_URL', 'https://api.minter.one/');

/*
 * You can configure the necessary parameters by default if you do not want to specify them each time you call the script
 */
const DEFAULT_BLOCK_START = 2942273;
const DEFAULT_BLOCK_STEP  = 720;            // 720 blocks ≈ 1 hour
const DEFAULT_COUNTS      = 10;

/*
 * How many decimal places should be left in all values?
 */
const PRECISION = 4;

/*
 * PIPs in BIP
 */
const PIPS = 1000000000000000000;

	// Max runtime in seconds
	set_time_limit(180);

    // Get parameters from the URL (if exists)
    $blockStart = isset($_GET['start']) ? (int) $_GET['start'] : DEFAULT_BLOCK_START;
    $blockStep  = isset($_GET['step'])  ? (int) $_GET['step']  : DEFAULT_BLOCK_STEP;
    $count      = isset($_GET['count']) ? (int) $_GET['count'] : DEFAULT_COUNTS;

    // Step counter in the loop
    $k = 0;

    // Data grabbing
    for($block = $blockStart; $block < ($blockStart + ($blockStep * $count)); $block += $blockStep) {
        
        // Get coin data
        $query = 'coin_info?symbol=' .COIN_NAME. '&height=' . $block;
        $coininfo = json_decode(file_get_contents(MSCAN_API_URL . $query));
        
        // Get coin price (through the sale of 1 coin per BIP)
        $query = 'estimate_coin_sell?coin_to_sell=' .COIN_NAME. '&coin_to_buy=BIP&value_to_sell=1000000000000000000&height=' . $block;
        $coinprice = json_decode(file_get_contents(MSCAN_API_URL . $query));

        // Get date and time of block. 
        // You can use the free node API here, so as not to pay for API requests that you can take for free
        $query = 'block?height=' . $block;
        $blockinfo = json_decode(file_get_contents(FREE_API_URL . $query));

        // Source time format as: 2019-11-04T08:41:16.466731612Z
        // Extract the first 19 characters and convert it to the standard date and time format
        $tstamp = date("Y-m-d H:i:s", strtotime(substr($blockinfo->result->time, 0, 19)));
        
        // Data package assembly
        $data = [
            'tstamp'  => $tstamp,
            'block'   => $block,
            'volume'  => round($coininfo->result->volume / PIPS, PRECISION),
            'reserve' => round($coininfo->result->reserve_balance / PIPS, PRECISION),
            'price'   => round($coinprice->result->will_get / PIPS, PRECISION)
        ];
        
        // Add a CSV data line with tab delimiters to the output file
        file_put_contents(OUTPUT_FILE, implode("\t", $data) . "\r\n", FILE_APPEND);
        
        // Showing realtime parsing progress in the browser
        echo ++$k . ') ' . $block . ' block passed...<br>';
        flush();
        ob_flush();
    }

    echo '<br>Done!';

?>