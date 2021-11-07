<?php
/**
 * OSTerrain50Reader provides elevation data for Great Britain by reading
 * a custom binary data file containing Ordnance Survey OS Terrain 50 data
 * 
 * See https://osola.org.uk/osterrain50 for more details
 * 
 * Requires PHP version 7.4 or higher
 * @author Bob Osola (bobosola@gmail.com)
 * Version 1.0.0
 */
class OSTerrain50Reader {

    /**
     * British National Grid constants
     * See https://en.wikipedia.org/wiki/Ordnance_Survey_National_Grid for more details
     */
    const GRIDS_PER_ROW_100 = 7;        // No. of grids per row in the full 91 grid block
    const METRES_IN_100_GRID = 100000;  // No of metres in 100 Km² grid E & N
    const METRES_IN_10_GRID = 10000;    // No of metres in 10 Km² grid E & N
    const DATA_COLS_IN_10_GRID = 10;    // No. of columns in a 10km² grid

    /**
     * Data file identifying sig
     */
    const FILE_SIG = 'OSTerrain50';

    /**
     * Data file header section
     */
    const MAX_NUM_DATA_FILES = 100;     // Maximum number of data files per 10km² grid
    const GRID_IDENT_LEN = 2;           // Length of a grid identifer ("SV" etc.)
    const ADDRESS_LENGTH = 4;           // Byte length of data addresses stored in the output file
    const HEADER_BLOCK_LENGTH = self::GRID_IDENT_LEN + (self::MAX_NUM_DATA_FILES * self::ADDRESS_LENGTH);

    /**
     * Data file data section
     */
    const ELEVATIONS_PER_COL = 200;     // No. of elevation values in each data column
    const ELEVATION_DATA_LENGTH = 2;    // Length of a single elvation data point
    const ELEVATION_DISTANCE = 50;      // Distance between successive elevations points

    /**
     * Public property
     * Set true to perform some optional debugging checks
     * Default: false
     */
    public $doChecks = false;

    /**
     * Public property
     * Set true to return some debugging error messages
     * Default: false
     */
    public $showErrors = false;

    /**
     * Path to binary data file
     */     
    private $dataFile;

    /**
     * File pointer for data reading
     */
    private $fp;

    /**
     * Array to hold stdClass location objects
     * as {easting: x, northing: y, elevation: z}
     */
    private $locations = Array();

    /**
     * Constructor
     * Provides the path to the binary data file
     * 
     * @param $dataFile    
     */
    function __construct($dataFile) {
        $this->dataFile = $dataFile;
    }
    
    /**
     * Retrieves OS Terrain 50 elevation values from a custom binary data file
     * 
     * @param array $userLocations   An array of stdClass location objects as
     *                               [{easting: x, northing: y, elevation: null}, ...]
     * 
     * @param bool $infill           Set true to generate infill locations every 50m
     *                               between each pair of userLocations
     * 
     * @return array                 An array of stdClass location objects populated
     *                               with elevation values
     */
    public function getElevations($userLocations, $infill) {

        // Optional debugging checks
        if ($this->doChecks) {
            $errorMessage = $this->doChecks();
            if ($errorMessage !== null) {
                return $errorMessage;
            }   
        } 

        // Ensure we have at least one location
        $numLocations = count($userLocations);
        if ($numLocations < 1) {
            return $this->handleError("Need at least one location in the locations parameter");
        }           
      
        // Create any optional intermediate infill locations
        if ($infill && ($numLocations > 1) ) {
            for ($i = 0; $i < $numLocations; $i++) {
                // Need at least two locations to prepare infills
                if ($i > 0) {
                    $includeStart = true;
                    if ($i >= 2) {
                        // Avoid double insertions where previous end == current start
                        $includeStart = false;
                    }
                    // Merge the results
                    array_push($this->locations, ...$this->get_infills($userLocations[$i - 1], $userLocations[$i], $includeStart));                  
                }
            }
        }
        else {
            // No infills required so just process the input locations
            $this->locations = $userLocations;
        }
        
        // Get the elevations values for each location
        $this->fp = fopen($this->dataFile, 'r');
        for ($i = 0; $i < count($this->locations); $i++) {

            // Work out how many grid blocks to jump over in the file header section.
            // NB: uses integer division to deliberately truncate the remainders - use floor(),
            // trunc() etc. in untyped languages

            // Reduce the coords down to obtain whole grid unit multipliers and apply them to calculate
            // the mumber of grids to jump over
            $e_cols = intdiv($this->locations[$i]->easting, self::METRES_IN_100_GRID);
            $n_rows = intdiv($this->locations[$i]->northing, self::METRES_IN_100_GRID);
            $grid_blocks_to_jump = (self::GRIDS_PER_ROW_100 * $n_rows) + $e_cols;

            // Calculate the offset from start of file to the start of the required grid block
            $grid_block_offset = strlen(self::FILE_SIG) + ($grid_blocks_to_jump * self::HEADER_BLOCK_LENGTH); 
            
            // Now work out how many data address placeholders to jump within the grid block section.
            $e_addr_cols = intdiv(($this->locations[$i]->easting % self::METRES_IN_100_GRID), self::METRES_IN_10_GRID);
            $n_addr_rows = intdiv(($this->locations[$i]->northing % self::METRES_IN_100_GRID), self::METRES_IN_10_GRID);
            $data_placeholders_to_jump = ($n_addr_rows * self::DATA_COLS_IN_10_GRID) + $e_addr_cols;  
    
            // Can now determine the individual data block address to jump to within the grid block
            $data_block_address_offset = 
                ($grid_block_offset + self::GRID_IDENT_LEN + ($data_placeholders_to_jump * self::ADDRESS_LENGTH));

            // Finally, work out the offset required to get to the desired elevation within a data block.
            // First reduce the coords to just the parts applicable in a 10 Km² data grid
            $data_easting = ($this->locations[$i]->easting % self::METRES_IN_100_GRID) % self::METRES_IN_10_GRID;
            $data_northing = ($this->locations[$i]->northing % self::METRES_IN_100_GRID) % self::METRES_IN_10_GRID; 

            // Then work out how many data rows and columns must be jumped (elevations are every 50m)
            $data_cols = intdiv($data_easting, self::ELEVATION_DISTANCE);
            $data_rows = intdiv($data_northing, self::ELEVATION_DISTANCE);
            $data_rows_to_jump = ($data_rows * self::ELEVATIONS_PER_COL) + $data_cols; 
            
            $elevation_offset = ($data_rows_to_jump * self::ELEVATION_DATA_LENGTH);

            // Jump to the location of the data block address
            fseek($this->fp, $data_block_address_offset);

            // Read the four byte data address value stored there
            $addressBytes = fread($this->fp, self::ADDRESS_LENGTH);
          
            // Unpack as 'V' for unsigned 32 bit integer (little endian) into associative array with key 'addr'
            $addressArray = unpack('Vaddr', $addressBytes);
            $data_block_address = $addressArray['addr'];
            if ($data_block_address != 0) {

                // Apply the required elevation data offset to the data block address and jump there
                $elev_addr = $data_block_address + $elevation_offset;
                fseek($this->fp, $elev_addr); 
                
                // Read the elevation data as two bytes
                $elevationBytes = fread($this->fp, self::ELEVATION_DATA_LENGTH);

                // The data is stored in little endian byte order so ideally we would unpack as 
                // 16 bit signed short little endian. However that option is not available
                // in PHP. So we must unpack as a 16 bit unsigned short little endian in order
                // to enforce reading the correct endianness on any machine but then check and
                // correct any negative values which are read wrongly, e.g. -7 is read as 65529

                // Unpack as 'v' for unsigned 16 bit integer (little endian) into 
                // associative array with key 'elev'
                $elevationArray = unpack('velev', $elevationBytes);

                // Because elevation data never has more than one decimal place, it's stored
                // as 10x actual value as a two byte integer for space-efficient storage 
                $elevation_x10 = $elevationArray['elev']; 

                // Correct any negative numbers due to unpacking as unsigned instead of signed
                if ($elevation_x10  >= pow(2, 15)) {
                    $elevation_x10 -= pow(2, 16); 
                }
                // Divide by 10 to return the elevation value
                $this->locations[$i]->elevation = $elevation_x10 / 10;          
            }
            else {
                // No data address means no data exists for this location, i.e. it's a 
                // sea area or an out-of-scope land mass, e.g. the Isle of Man
                $this->locations[$i]->elevation = 0;
            }
        }
        fclose($this->fp);           
        return $this->locations;      
    }

    /**
     * Creates infill locations approx. 50m apart between the two parameter locations
     * 
     * @param location $start      The start location
     * @param location $end        The finish location
     * @param bool $includeStart   Whether to include the start location in the output
     *                             in order to avoid double insertions when later merging
     *                             the infilled locations
     * 
     * @return array               Array of locations approx 50m apart
     */
    private function get_infills($start, $end, $includeStart) {

    /*
        Example: for 4 locations requiring infills:
        1---2               get_infills(1, 2, true)  returns 1st, infills & 2nd location
            ---3            get_infills(2, 3, false) returns infills & 3rd location
                ---4        get_infills(3, 4, false) returns infills & 4th location
        so merging the three results contains 4 locations, infills, and no duplicates
    
        Example: for 2 locations where start and end are 200m apart:
                                   • end
                               •   |
            diagonal_diff  •       |  northing_diff
                       •           |
             start •_______________|
                    easting_diff

            • 3 infill coords are required
            • 5 coords are returned if $includeStart= true
    */

        // Build the output array
        $coords = array();

        if ($includeStart) {
            $coords[] = $start;
        }

        // NB: work in floats for cumulative calcs to avoid rounding
        // innaccuracies which become noticeable over long distances 
        
        // Get the diagonal difference between the start and end coords
        (float)$easting_diff = $end->easting - $start->easting;
        (float)$northing_diff = $end->northing - $start->northing;
        (float)$diagonal_diff = sqrt(($easting_diff * $easting_diff) + ($northing_diff * $northing_diff));

        // Only create infills where the two locations are greater than 50m apart
        if ($diagonal_diff > self::ELEVATION_DISTANCE) {

            // Get the infill easting & northing deltas
            // as a proportion of the infill diagonal diff
            $infill_diag_diff = $diagonal_diff / self::ELEVATION_DISTANCE;
            (float)$delta_east = $easting_diff / $infill_diag_diff;
            (float)$delta_north = $northing_diff / $infill_diag_diff;

            // Cumulativley add the delta_east & delta_north diffs
            // to create the required number of infill locations

            // Begin with the start location
            $cumulative_east = $start->easting;
            $cumulative_north = $start->northing;

            // Get the number of infills required
            $infills_required = ceil($infill_diag_diff) -1; 

            // Create the infill locations
            for ($i = 0; $i < $infills_required; $i++) {

                $cumulative_east += $delta_east;
                $cumulative_north += $delta_north;

                // Prepare an object to hold the generated infill location
                $infill_coord = new stdClass;

                // Store the infill location rounded to integer values
                $infill_coord->easting = (int)round($cumulative_east);
                $infill_coord->northing = (int)round($cumulative_north);
                $coords[] = $infill_coord;
            }
        }
        // Add the end location
        $coords[] = $end;
        return $coords;
    }

    /**
     * Some optional sanity checks for use while debugging.
     * These are not necessary once a valid readable data file exists
     * 
     *  @return string
     */
    private function doChecks() {

        // Check the data file exists
        if (!file_exists($this->dataFile)){
            return $this->handleError("The data file {$this->dataFile} does not exist");
        }

        // Check we have at least read perms
        $this->fp = fopen($this->dataFile, 'r');
        if ($this->fp === false) {
            return $this->handleError("Could not open the data file {$this->dataFile} for reading");
        } 

        // Check it's a valid OS Terrain 50 data file by reading the file sig
        rewind($this->fp);
        fseek($this->fp, 0);
        $sigLength = strlen(self::FILE_SIG);
        $sigBytes = fread($this->fp, $sigLength);
        $sig = unpack("A{$sigLength}sig", $sigBytes);
        if (self::FILE_SIG != $sig['sig']) {
            return $this->handleError("The file {$this->dataFile} is not a valid OS Terrain 50 binary data file");
        }          
        return null;
    }

    /**
     * Basic error message handler for debugging purposes
     * 
     * @param string $message - an error message to be returned
     * @return string - a JSON-encoded string
     */
    private function handleError($message) {
        if ($this->showErrors) {    
            return(json_encode(array('Error' => "$message")));
        }
        else {
            return("{}");
        }
    }
}