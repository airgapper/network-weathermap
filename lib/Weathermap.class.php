<?php
// PHP Weathermap 0.98
// Copyright Howard Jones, 2005-2014 howie@thingy.com
// http://www.network-weathermap.com/
// Released under the GNU Public License

class WeatherMap extends WeatherMapBase
{
    var $nodes = array(); // an array of WeatherMapNodes
    var $links = array(); // an array of WeatherMapLinks
    var $texts = array(); // an array containing all the extraneous text bits
    var $used_images = array(); // an array of image filenames referred to (used by editor)
    var $seen_zlayers = array(0 => array(), 1000 => array()); // 0 is the background, 1000 is the legends, title, etc

    var $scales = array(); // the new array of WMScale objects

    var $next_id;

    var $background;
    var $htmlstyle;
    var $imap;
    var $colours;
    var $configfile;
    var $imagefile,
        $imageuri;
    var $rrdtool;

    var $kilo;
    var $sizedebug,
        $widthmod,
        $debugging;
    var $keyfont,
        $timefont;

    var $width, $height;

    var $keyx, $keyy, $keyimage, $keytext; // arrays

    var $titlex,
        $titley;
    var $title,
        $titlefont;

    var $htmloutputfile,
        $imageoutputfile;
    var $dataoutputfile;

    var $htmlstylesheet;

    var $defaultlink,
        $defaultnode;

    var $need_size_precalc;
    var $keystyle, $keysize;
    var $rrdtool_check;
    var $inherit_fieldlist;


    var $min_ds_time;
    var $max_ds_time;
    var $min_data_time, $max_data_time;
    var $timex, $timey;
    var  $stamptext, $datestamp;
    var $mintimex, $maxtimex;
    var $mintimey, $maxtimey;
    var $minstamptext, $maxstamptext;

    var $context;
    var $name;
    var $black,
        $white,
        $grey,
        $selected;

    var $activeDataSourceClasses;
    var $thumb_width, $thumb_height;
    var $has_includes;
    var $has_overlibs;
    var $dsinfocache = array();

    var $plugins = array();
    var $included_files = array();
    var $usage_stats = array();
    var $colourtable = array();
    var $fonts = null;
    var $warncount = 0;
    var $jsincludes = array();
    var $parent = null;

    var $config = array();
    var $runtime = array();

    function WeatherMap()
    {
        $this->inherit_fieldlist = array
        (
            'width' => 800,
            'height' => 600,
            'kilo' => 1000,
            'numscales' => array('DEFAULT' => 0),
            'datasourceclasses' => array(),
            'preprocessclasses' => array(),
            'postprocessclasses' => array(),
            'included_files' => array(),
            'context' => '',
            'dumpconfig' => false,
            'rrdtool_check' => '',
            'background' => '',
            'imageoutputfile' => '',
            'dataoutputfile' => '',
            'imageuri' => '',
            'htmloutputfile' => '',
            'htmlstylesheet' => '',
            'labelstyle' => 'percent', // redundant?
            'htmlstyle' => 'static',
            'keystyle' => array('DEFAULT' => 'classic'),
            'title' => 'Network Weathermap',
            'keytext' => array('DEFAULT' => 'Traffic Load'),
            'keyx' => array('DEFAULT' => -1),
            'keyy' => array('DEFAULT' => -1),
            'keyimage' => array(),
            'keysize' => array('DEFAULT' => 400),
            'stamptext' => 'Created: %b %d %Y %H:%M:%S',
            'keyfont' => 4,
            'titlefont' => 2,
            'timefont' => 2,
            'timex' => 0,
            'timey' => 0,
            'fonts' => null,

            'mintimex' => -10000,
            'mintimey' => -10000,
            'maxtimex' => -10000,
            'maxtimey' => -10000,
            'minstamptext' => 'Oldest Data: %b %d %Y %H:%M:%S',
            'maxstamptext' => 'Newest Data: %b %d %Y %H:%M:%S',

            'thumb_width' => 0,
            'thumb_height' => 0,
            'titlex' => -1,
            'titley' => -1,
            'cachefolder' => 'cached',
            'mapcache' => '',
            'sizedebug' => false,
            'debugging' => false,
            'widthmod' => false,
            'has_includes' => false,
            'has_overlibs' => false,
            'name' => 'MAP'
        );

        $this->reset();

    }

    function my_type()
    {
        return "MAP";
    }

    function reset()
    {
        $this->next_id = 100;

        foreach (array_keys($this->inherit_fieldlist) as $fld) {
            $this->$fld = $this->inherit_fieldlist[$fld];
        }

        $this->min_ds_time = null;
        $this->max_ds_time = null;

        $this->nodes = array(); // an array of WeatherMapNodes
        $this->links = array(); // an array of WeatherMapLinks

        // these are the default defaults
        // by putting them into a normal object, we can use the
        // same code for writing out LINK DEFAULT as any other link.
        wm_debug("Creating ':: DEFAULT ::' DEFAULT LINK\n");

        // these two are used for default settings
        $deflink = new WeatherMapLink;
        $deflink->name = ":: DEFAULT ::";
        $deflink->template = ":: DEFAULT ::";
        $deflink->reset($this);

        $this->links[':: DEFAULT ::'] = & $deflink;

        wm_debug("Creating ':: DEFAULT ::' DEFAULT NODE\n");
        $defnode = new WeatherMapNode;
        $defnode->name = ":: DEFAULT ::";
        $defnode->template = ":: DEFAULT ::";
        $defnode->reset($this);

        $this->nodes[':: DEFAULT ::'] = & $defnode;

        $this->scales = array();
        $this->scales['DEFAULT'] = new WeatherMapScale("DEFAULT", $this);

        $this->colourtable = array();

        // ************************************
        // now create the DEFAULT link and node, based on those.
        // these can be modified by the user, but their template (and therefore comparison in WriteConfig) is ':: DEFAULT ::'
        wm_debug("Creating actual DEFAULT NODE from :: DEFAULT ::\n");
        $defnode2 = new WeatherMapNode;
        $defnode2->name = "DEFAULT";
        $defnode2->template = ":: DEFAULT ::";
        $defnode2->reset($this);

        $this->nodes['DEFAULT'] = & $defnode2;

        wm_debug("Creating actual DEFAULT LINK from :: DEFAULT ::\n");
        $deflink2 = new WeatherMapLink;
        $deflink2->name = "DEFAULT";
        $deflink2->template = ":: DEFAULT ::";
        $deflink2->reset($this);

        $this->links['DEFAULT'] = & $deflink2;

        assert('is_object($this->nodes[":: DEFAULT ::"])');
        assert('is_object($this->links[":: DEFAULT ::"])');
        assert('is_object($this->nodes["DEFAULT"])');
        assert('is_object($this->links["DEFAULT"])');

        // ************************************

        $this->imap = new HTML_ImageMap('weathermap');
        $this->colours = array();
        $this->colourtable = array();

        wm_debug("Adding default map colour set.\n");
        $defaults = array(
            'KEYTEXT' => array('bottom' => -2, 'top' => -1, 'red' => 0, 'green' => 0, 'blue' => 0),
            'KEYOUTLINE' => array('bottom' => -2, 'top' => -1, 'red' => 0, 'green' => 0, 'blue' => 0),
            'KEYBG' => array('bottom' => -2, 'top' => -1, 'red' => 255, 'green' => 255, 'blue' => 255),
            'BG' => array('bottom' => -2, 'top' => -1, 'red' => 255, 'green' => 255, 'blue' => 255),
            'TITLE' => array('bottom' => -2, 'top' => -1, 'red' => 0, 'green' => 0, 'blue' => 0),
            'TIME' => array('bottom' => -2, 'top' => -1, 'red' => 0, 'green' => 0, 'blue' => 0)
        );

        foreach ($defaults as $key => $def) {
            $this->colourtable[$key] = new WMColour($def['red'], $def['green'], $def['blue']);
        }

        $this->configfile = '';
        $this->imagefile = '';
        $this->imageuri = '';

        $this->fonts = new WMFontTable();
        $this->fonts->init();

        $this->loadPlugins('data', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'datasources');
        $this->loadPlugins('pre', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'pre');
        $this->loadPlugins('post', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'post');

        wm_debug("WeatherMap class Reset() complete\n");
    }

    /**
     * The old way to render strings in a particular font.
     * @deprecated use WMFont instead
     *
     * @param $image
     * @param $fontnumber
     * @param $x
     * @param $y
     * @param $string
     * @param $colour
     * @param int $angle
     * @return mixed
     */
    function myimagestring($image, $fontnumber, $x, $y, $string, $colour, $angle = 0)
    {
        // throw new WMException("Still using myimagestring");
        $fontObject = $this->fonts->getFont($fontnumber);
        return $fontObject->drawImageString($image, $x, $y, $string, $colour, $angle);

    }

    /**
     * The old way to get font metrics.
     * @deprecated use WMFont instead
     *
     * @param $fontNumber
     * @param $string
     * @return mixed
     */
    function myimagestringsize($fontNumber, $string)
    {
        $fontObject = $this->fonts->getFont($fontNumber);
        return $fontObject->calculateImageStringSize($string);
    }

    function processString($input, &$context, $include_notes = true, $multiline = false)
    {
        if ($input === '') {
            return '';
        }

        // don't bother with all this regexp rubbish if there's nothing to match
        if (false === strpos($input, "{")) {
            return $input;
        }

        $the_item = null;
        $context_type = strtolower($context->my_type());

        if ($context_type != "map") {
            $context_description = $context_type . ":" . $context->name;
        } else {
            $context_description = $context_type;
        }

        // next, shortcut all the regexps for very common tokens
        if ($context_type === 'node') {
            $input = str_replace("{node:this:graph_id}", $context->get_hint("graph_id"), $input);
            $input = str_replace("{node:this:name}", $context->name, $input);
        }

        if ($context_type === 'link') {
            $input = str_replace("{link:this:graph_id}", $context->get_hint("graph_id"), $input);
            $input = str_replace("{link:this:name}", $context->name, $input);

            if (false !== strpos($input, "{")) {
                // TODO - shortcuts for standard bwlabel formats, if there is still a token

                //if (strpos($input,"{link:this:bandwidth_in:%2k}") !== false) {
                //    $temp = mysprintf("%2k",$context->hints['bandwidth_in']);
                //    $input = str_replace("{link:this:bandwidth_in:%2k}", $temp, $input);
                //}

                // define('FMT_BITS_IN',"{link:this:bandwidth_in:%2k}");
                // define('FMT_BITS_OUT',"{link:this:bandwidth_out:%2k}");
                // define('FMT_UNFORM_IN',"{link:this:bandwidth_in}");
                // define('FMT_UNFORM_OUT',"{link:this:bandwidth_out}");
                // define('FMT_PERC_IN',"{link:this:inpercent:%.2f}%");
                // define('FMT_PERC_OUT',"{link:this:outpercent:%.2f}%");
            }
        }

        // check if we can now quit early before the regexp stuff
        if (false === strpos($input, "{")) {
            return $input;
        }

        $output = $input;

        while (preg_match('/(\{(?:node|map|link)[^}]+\})/', $input, $matches)) {
            $value = "[UNKNOWN]";
            $format = "";
            $key = $matches[1];

            if (preg_match('/\{(node|map|link):([^}]+)\}/', $key, $matches)) {
                $type = $matches[1];
                $args = $matches[2];

                if ($type == 'map') {
                    $the_item = $this;
                    if (preg_match('/map:([^:]+):*([^:]*)/', $args, $matches)) {
                        $args = $matches[1];
                        $format = $matches[2];
                    }
                }

                if (($type == 'link') || ($type == 'node')) {
                    if (preg_match("/([^:]+):([^:]+):*([^:]*)/", $args, $matches)) {
                        $itemname = $matches[1];
                        $args = $matches[2];
                        $format = $matches[3];

                        $the_item = null;
                        if (($itemname == "this") && ($type == strtolower($context->my_type()))) {
                            $the_item = $context;
                        } elseif (strtolower($context->my_type()) == "link" && $type == 'node' && ($itemname == '_linkstart_' || $itemname == '_linkend_')) {
                            // this refers to the two nodes at either end of this link
                            if ($itemname == '_linkstart_') {
                                $the_item = $context->a;
                            }

                            if ($itemname == '_linkend_') {
                                $the_item = $context->b;
                            }
                        } elseif (($itemname == "parent") && ($type == strtolower($context->my_type())) && ($type == 'node') && ($context->relative_to != '')) {
                            $the_item = $this->nodes[$context->relative_to];
                        } else {
                            if (($type == 'link') && isset($this->links[$itemname])) {
                                $the_item = $this->links[$itemname];
                            }
                            if (($type == 'node') && isset($this->nodes[$itemname])) {
                                $the_item = $this->nodes[$itemname];
                            }
                        }
                    }
                }

                if (is_null($the_item)) {
                    wm_warn("ProcessString: $key refers to unknown item (context is $context_description) [WMWARN05]\n");
                } else {
                    wm_debug("ProcessString: Found appropriate item: " . get_class($the_item) . " " . $the_item->name . "\n");

                    $value = $this->findItemValue($the_item, $args, $value, $include_notes);
                }
            }

            // format, and sanitise the value string here, before returning it

            // if ($value===null) $value='null';

            wm_debug("ProcessString: replacing %s with %s \n", $key, $value);

            if ($format != '') {
                $value = wmSprintf($format, $value, $this->kilo);
                wm_debug("ProcessString: formatted $format to $value\n");
            }

            $input = str_replace($key, '', $input);
            $output = str_replace($key, $value, $output);
        }
        return ($output);
    }

    function findItemValue(&$mapItem, $variableName, $currentValue, $includeNotes = true)
    {
        // SET and notes have precedent over internal properties
        // this is my laziness - it saves me having a list of reserved words
        // which are currently used for internal props. You can just 'overwrite' any of them.
        if (array_key_exists($variableName, $mapItem->hints)) {
            wm_debug("ProcessString: used hint\n");
            return $mapItem->hints[$variableName];
        }

        if ($includeNotes && array_key_exists($variableName, $mapItem->notes)) {
            // for some things, we don't want to allow notes to be considered.
            // mainly - TARGET (which can define command-lines), shouldn't be
            // able to get data from uncontrolled sources (i.e. data sources rather than SET in config files).
            wm_debug("ProcessString: used note\n");
            return $mapItem->notes[$variableName];
        }

        // Previously this was directly accessing properties of map items
        try {
            $value = $mapItem->getValue($variableName);
        } catch (WMException $e) {
            // give up, and pass back the current value
            return $currentValue;
        }

        wm_debug("ProcessString: used internal property\n");
        return $value;
    }

    /**
     * Fill in the links with random bandwidth data. Basically for a "demo mode" for
     * the command-line tool.
     */
    function populateRandomData()
    {
        foreach ($this->links as $link) {
            $this->links[$link->name]->bandwidth_in = rand(0, $link->max_bandwidth_in);
            $this->links[$link->name]->bandwidth_out = rand(0, $link->max_bandwidth_out);
        }
    }

    /**
     * Search a directory for plugin class files, and load them. Each one is then
     * instantiated once, and saved into the map object.
     *
     * @param string $type - Which kind of plugin are we loading?
     * @param string $dir - Where to load from?
     */
    private function loadPlugins($type = "data", $dir = "lib/datasources")
    {
        wm_debug("Beginning to load $type plugins from $dir\n");

        if (!file_exists($dir)) {
            $dir = dirname(__FILE__) . DIRECTORY_SEPARATOR . $dir;
            wm_debug("Relative path didn't exist. Trying $dir\n");
        }
        $directoryHandle = @opendir($dir);

        if (!$directoryHandle) { // try to find it with the script, if the relative path fails
            $srcdir = substr($_SERVER['argv'][0], 0, strrpos($_SERVER['argv'][0], DIRECTORY_SEPARATOR));
            $directoryHandle = opendir($srcdir . DIRECTORY_SEPARATOR . $dir);
            if ($directoryHandle) {
                $dir = $srcdir . DIRECTORY_SEPARATOR . $dir;
            }
        }

        if (!$directoryHandle) {
            wm_warn("Couldn't open $type Plugin directory ($dir). Things will probably go wrong. [WMWARN06]\n");
            return;
        }

        while ($file = readdir($directoryHandle)) {
            $realfile = $dir . DIRECTORY_SEPARATOR . $file;

            if (is_file($realfile) && preg_match('/\.php$/', $realfile)) {
                wm_debug("Loading $type Plugin class from $file\n");

                include_once($realfile);
                $class = preg_replace("/\.php$/", "", $file);

                wm_debug("Loaded $type Plugin class $class from $file\n");

                $this->plugins[$type][$class]['object'] = new $class;
                $this->plugins[$type][$class]['active'] = true;

                if (!isset($this->plugins[$type][$class])) {
                    wm_debug("** Failed to create an object for plugin $type/$class\n");
                    $this->plugins[$type][$class]['active'] = false;
                } else {
                    wm_debug("Instantiated $class.\n");
                }
            } else {
                wm_debug("Skipping $file\n");
            }
        }
    }

    /**
     * Loop through the datasource plugins, allowing them to initialise any internals.
     * The plugins can also refuse to run, if resources they need aren't available.
     */
    private function initialiseAllPlugins()
    {
        wm_debug("Running Init() for Data Source Plugins...\n");

        foreach (array('data','pre','post') as $type) {
            wm_debug("Initialising $type Plugins...\n");

            foreach ($this->plugins[$type] as $name => $pluginEntry) {
                wm_debug("Running $name" . "->Init()\n");

                $ret = $pluginEntry['object']->Init($this);

                if (!$ret) {
                    wm_debug("Marking $name plugin as inactive, since Init() failed\n");
                    $pluginEntry['active'] = false;
                }
            }
        }
        wm_debug("Finished Initialising Plugins...\n");
    }

    /**
     * Create an array of all the nodes and links, mixed together.
     * readData() makes several passes through this list.
     *
     * @return array
     */
    private function buildAllItemsList()
    {
        $allItems = array();

        $listOfItemLists = array(&$this->links, &$this->nodes);
        reset($listOfItemLists);

        while (list($outerListCount,) = each($listOfItemLists)) {
            unset($itemList);
            $itemList = &$listOfItemLists[$outerListCount];

            reset($itemList);
            while (list($innerListCount,) = each($itemList)) {
                unset($oneMapItem);
                $oneMapItem = &$itemList[$innerListCount];
                $allItems []= $oneMapItem;
            }
        }
        return $allItems;
    }

    /**
     * For each mapitem, loop through all its targets and find a plugin
     * that recognises them. Then register the target with the plugin
     * so that it can potentially pre-fetch or optimise in some way.
     *
     * @param $itemList
     */
    private function preProcessTargets($itemList)
    {
        wm_debug("Preprocessing targets\n");

        foreach ($itemList as $oneMapItem) {
            if ($oneMapItem->isTemplate()) {
                continue;
            }

            foreach ($oneMapItem->targets as $target) {
                wm_debug("ProcessTargets: New Target: $target\n");

                $target->preProcess($oneMapItem, $this);
                $matchedBy = $target->findHandlingPlugin($this->plugins['data']);

                if ($matchedBy != "") {
                    if ($this->plugins['data'][$matchedBy]['active']) {
                        $target->registerWithPlugin($this, $oneMapItem);
                    } else {
                        wm_warn(sprintf(
                            "ProcessTargets: %s %s, target: %s was recognised as a valid TARGET by a plugin that is unable to run (%s) [WMWARN07]\n",
                            $oneMapItem->my_type(),
                            $oneMapItem->name,
                            $target,
                            $matchedBy
                        ));
                    }
                }

                if ($matchedBy == "") {
                    wm_warn(sprintf(
                        "ProcessTargets: %s, %s, target: %s was not recognised as a valid TARGET [WMWARN08]\n",
                        $oneMapItem->my_type(),
                        $oneMapItem->name,
                        $target
                    ));
                }
            }
        }
    }

    private function readDataFromTargets($itemList)
    {
        wm_debug("======================================\n");
        wm_debug("Starting main collection loop\n");

        $channels = array(IN,OUT);

        foreach ($itemList as $mapItem) {
            $type = $mapItem->my_type();
            $name = $mapItem->name;

            $totals = array();
            foreach ($channels as $channel) {
                $totals[$channel] = 0;
            }
            $datatime = 0;

            wm_debug("-------------------------------------------------------------\n");
            wm_debug("ReadData for $type $name: \n");

            if ($mapItem->isTemplate()) {
                wm_debug("ReadData: Skipping $type $name that looks like a template\n.");
                continue;
            }

            $nTargets = count($mapItem->targets);

            foreach ($mapItem->targets as $target) {
                wm_debug("ReadData: New Target: $target\n");
                $target->readData($this, $mapItem);

                if ($target->hasValidData()) {
                    $results = $target->getData();
                    $datatime = array_shift($results);

                    foreach ($channels as $channel) {
                        wm_debug("Adding %f to total for channel %d\n", $results[$channel], $channel);
                        $totals[$channel] += $results[$channel];
                    }
                    wm_debug("Running totals: %s\n", join(" ", $totals));
                } else {
                    wm_debug("Invalid data?\n");
                }

                // This was the only target, and it's failed
                if (!$target->hasValidData() && $nTargets == 1) {
                    // this is to allow null to be passed through from DS plugins in the case of a single target
                    // we've never defined what x + null is, so we'll treat that as a 0

                    foreach ($channels as $channel) {
                        $totals[$channel] = null;
                    }
                }

                # keep a track of the range of dates for data sources (mainly for MRTG/textfile based DS)
                if ($datatime > 0) {
                    if ($this->max_data_time == null || $datatime > $this->max_data_time) {
                        $this->max_data_time = $datatime;
                    }
                    if ($this->min_data_time == null || $datatime < $this->min_data_time) {
                        $this->min_data_time = $datatime;
                    }

                    wm_debug("DataTime MINMAX: " . $this->min_data_time . " -> " . $this->max_data_time . "\n");
                }
            }

            wm_debug("ReadData complete for %s %s: %s\n", $type, $name, join(" ", $totals));

            // NOTE - this part still happens even if there were no targets

            // bodge for now: TODO these should be set in Reset() and readConfig()
            $mapItem->maxValues[IN] = $mapItem->max_bandwidth_in;
            $mapItem->maxValues[OUT] = $mapItem->max_bandwidth_out;

            $mapItem->bandwidth_in = $totals[IN];
            $mapItem->bandwidth_out = $totals[OUT];
            // TODO this should replace the above 2 lines
            foreach ($channels as $channel) {
                $mapItem->absoluteUsages[$channel] = $totals[$channel];
                $mapItem->percentUsages[$channel] = ($totals[$channel] / $mapItem->maxValues[$channel]) * 100;
            }
            $mapItem->outpercent = (($totals[OUT]) / ($mapItem->max_bandwidth_out)) * 100;
            $mapItem->inpercent = (($totals[IN]) / ($mapItem->max_bandwidth_in)) * 100;

            if ($type == 'LINK' && $mapItem->duplex == 'half') {
                // in a half duplex link, in and out share a common bandwidth pool, so percentages need to include both
                wm_debug("Calculating percentage using half-duplex\n");
                $mapItem->outpercent = (($totals[IN] + $totals[OUT]) / ($mapItem->max_bandwidth_out)) * 100;
                $mapItem->inpercent = (($totals[IN] + $totals[OUT]) / ($mapItem->max_bandwidth_in)) * 100;

                if ($mapItem->max_bandwidth_out != $mapItem->max_bandwidth_in) {
                    wm_warn("ReadData: $type $name: You're using asymmetric bandwidth AND half-duplex in the same link. That makes no sense. [WMWARN44]\n");
                }
            }

            $warn_in = true;
            $warn_out = true;
            if ($type == 'NODE' && $mapItem->scalevar == 'in') {
                $warn_out = false;
            }
            if ($type == 'NODE' && $mapItem->scalevar == 'out') {
                $warn_in = false;
            }

            if ($mapItem->scaletype == 'percent') {
                list($incol, $inscalekey, $inscaletag) = $this->colourFromValue($mapItem->inpercent, $mapItem->usescale, $mapItem->name, true, $warn_in);
                list($outcol, $outscalekey, $outscaletag) = $this->colourFromValue($mapItem->outpercent, $mapItem->usescale, $mapItem->name, true, $warn_out);

                foreach ($channels as $channel) {
                    list($col, $scalekey, $scaletag) = $this->colourFromValue($mapItem->percentUsages[$channel], $mapItem->usescale, $mapItem->name, true, $warn_out);
                    $mapItem->channelScaleColours[$channel] = $col;
                    $mapItem->colours[$channel] = $col;
                }
            } else {
                // use absolute values, if that's what is requested
                list($incol, $inscalekey, $inscaletag) = $this->colourFromValue($mapItem->bandwidth_in, $mapItem->usescale, $mapItem->name, false, $warn_in);
                list($outcol, $outscalekey, $outscaletag) = $this->colourFromValue($mapItem->bandwidth_out, $mapItem->usescale, $mapItem->name, false, $warn_out);

                foreach ($channels as $channel) {
                    list($col, $scalekey, $scaletag) = $this->colourFromValue($mapItem->absoluteUsages[$channel], $mapItem->usescale, $mapItem->name, false, $warn_out);
                    $mapItem->channelScaleColours[$channel] = $col;
                    $mapItem->colours[$channel] = $col;
                }
            }

            $mapItem->add_note("inscalekey", $inscalekey);
            $mapItem->add_note("outscalekey", $outscalekey);

            $mapItem->add_note("inscaletag", $inscaletag);
            $mapItem->add_note("outscaletag", $outscaletag);

            $mapItem->add_note("inscalecolor", $incol->asHTML());
            $mapItem->add_note("outscalecolor", $outcol->asHTML());

            $mapItem->colours[IN] = $incol;
            $mapItem->colours[OUT] = $outcol;

            wm_debug(sprintf("ReadData: Setting %s,%s for %s\n", wm_value_or_null($totals[IN]), wm_value_or_null($totals[OUT]), $name));
            unset($mapItem);
        }
    }

    private function prefetchPlugins()
    {
        // give all the plugins a chance to prefetch their results
        wm_debug("======================================\n");
        wm_debug("Starting prefetch\n");
        foreach ($this->plugins['data'] as $name => $pluginEntry) {
            $pluginEntry['object']->Prefetch($this);
        }
    }

    private function cleanupPlugins($type)
    {
        wm_debug("======================================\n");
        wm_debug("Starting cleanup\n");

        foreach ($this->plugins[$type] as $name => $pluginEntry) {
            $pluginEntry['object']->CleanUp($this);
        }
    }

    function readData()
    {
        // we skip readdata completely in sizedebug mode
        if ($this->sizedebug != 0) {
            wm_debug("Size Debugging is on. Skipping readData.\n");
            return;
        }

        wm_debug("======================================\n");
        wm_debug("ReadData: Updating link data for all links and nodes\n");

        $allMapItems = $this->buildAllItemsList();

        $this->initialiseAllPlugins();

        // process all the targets and find a plugin for them
        $this->preProcessTargets($allMapItems);

        $this->prefetchPlugins();

        $this->readDataFromTargets($allMapItems);

        $this->cleanupPlugins('data');

        wm_debug("ReadData Completed.\n");
        wm_debug("------------------------------\n");

    }

    protected static function () {

    }


    // This should be in WeatherMapScale - All scale lookups are done here
    function colourFromValue($value, $scalename = "DEFAULT", $name = "", $is_percent = true, $scale_warning = true)
    {
        $tag = '';
        $matchsize = null;
        $matchkey = null;

        $nowarn_clipping = intval($this->get_hint("nowarn_clipping"));
        $nowarn_scalemisses = (!$scale_warning) || intval($this->get_hint("nowarn_scalemisses"));

        if (isset($this->colours[$scalename])) {
            $colours = $this->colours[$scalename];

            if ($is_percent && $value > 100) {
                if ($nowarn_clipping == 0) {
                    wm_warn("ColourFromValue: Clipped $value% to 100% for item $name [WMWARN33]\n");
                }
                $value = 100;
            }

            if ($is_percent && $value < 0) {
                if ($nowarn_clipping == 0) {
                    wm_warn("ColourFromValue: Clipped $value% to 0% for item $name [WMWARN34]\n");
                }
                $value = 0;
            }

            foreach ($colours as $key => $scaleEntry) {
                if ((!isset($scaleEntry['special']) || $scaleEntry['special'] == 0)
                    and ($value >= $scaleEntry['bottom'])
                    and ($value <= $scaleEntry['top'])
                ) {
                    $range = $scaleEntry['top'] - $scaleEntry['bottom'];

                    // change in behaviour - with multiple matching ranges for a value, the smallest range wins
                    if (is_null($matchsize) || ($range < $matchsize)) {
                        $matchsize = $range;
                        $matchkey = $key;

                        # wm_debug("CFV $name $scalename $value '$tag' $key $r $g $b\n");
                    }
                }
            }

            // TODO: this should use the 'c1' property, not the individual RGB properties

            // if we have a match, figure out the actual
            // colour (for gradients) and return it
            if (!is_null($matchsize)) {
                $scaleEntry = $colours[$matchkey];

                if (isset($scaleEntry['c2'])) {
                    if ($scaleEntry["bottom"] == $scaleEntry["top"]) {
                        $ratio = 0;
                    } else {
                        $ratio = ($value - $scaleEntry["bottom"]) / ($scaleEntry["top"] - $scaleEntry["bottom"]);
                    }

                    $col = $scaleEntry['c1']->blendWith($scaleEntry['c2'], $ratio);
                } else {
                    $col = $scaleEntry['c1'];
                }

                if (isset($scaleEntry['tag'])) {
                    $tag = $scaleEntry['tag'];
                }
                return (array($col, $matchkey, $tag));
            }

        } else {
            if ($scalename != 'none') {
                wm_warn("ColourFromValue: Attempted to use non-existent scale: $scalename for item $name [WMWARN09]\n");
            } else {
                return array(new WMColour(255, 255, 255), '', '');
            }
        }

        // shouldn't really get down to here if there's a complete SCALE

        // you'll only get grey for a COMPLETELY quiet link if there's no 0 in the SCALE lines
        if ($value == 0) {
            return array(new WMColour(192, 192, 192), '', '');
        }

        if ($nowarn_scalemisses == 0) {
            wm_warn("ColourFromValue: Scale $scalename doesn't include a line for $value" . ($is_percent ? "%" : "") . " while drawing item $name [WMWARN29]\n");
        }

        // and you'll only get white for a link with no colour assigned
        return array(new WMColour(255, 255, 255), '', '');
    }


    private function scaleEntrySort($a, $b)
    {
        if ($a['bottom'] == $b['bottom']) {
            if ($a['top'] < $b['top']) {
                return -1;
            }
            if ($a['top'] > $b['top']) {
                return 1;
            }
            return 0;
        }

        if ($a['bottom'] < $b['bottom']) {
            return -1;
        }

        return 1;
    }


    private function drawLegendHorizontal($gdTargetImage, $scaleName = "DEFAULT", $keyWidth = 400)
    {
        $title = $this->keytext[$scaleName];

        $nScales = $this->numscales[$scaleName];

        wm_debug("Drawing $nScales colours into SCALE\n");

        # $font = $this->keyfont;
        $fontObject = $this->fonts->getFont($this->keyfont);

        $x = 0;
        $y = 0;

        $scaleFactor = $keyWidth / 100;

        list($tileWidth, $tileHeight) = $fontObject->calculateImageStringSize("100%");

        $boxLeft = $x;
        $scaleLeft = $boxLeft + 4 + $scaleFactor / 2;
        $boxRight = $scaleLeft + $keyWidth + $tileWidth + 4 + $scaleFactor / 2;

        $boxTop = $y;
        $scaleTop = $boxTop + $tileHeight + 6;
        $scaleBottom = $scaleTop + $tileHeight * 1.5;
        $boxBottom = $scaleBottom + $tileHeight * 2 + 6;

        $gdScaleImage = imagecreatetruecolor($boxRight + 1, $boxBottom + 1);
        $scaleReference = 'gdref_legend_' . $scaleName;

        // Start with a transparent box, in case the fill or outline colour is 'none'
        imageSaveAlpha($gdScaleImage, true);
        $transparentColour = imagecolorallocatealpha($gdScaleImage, 128, 0, 0, 127);
        imagefill($gdScaleImage, 0, 0, $transparentColour);

        $this->preAllocateScaleColours($gdScaleImage, $scaleReference);

        $bgColour = $this->colourtable['KEYBG'];
        $outlineColour = $this->colourtable['KEYOUTLINE'];

        wm_debug("BG is $bgColour, Outline is $outlineColour\n");

        if ($bgColour->isRealColour()) {
            imagefilledrectangle($gdScaleImage, $boxLeft, $boxTop, $boxRight, $boxBottom, $bgColour->gdAllocate($gdScaleImage));
        }

        if ($outlineColour->isRealColour()) {
            imagerectangle($gdScaleImage, $boxLeft, $boxTop, $boxRight, $boxBottom, $outlineColour->gdAllocate($gdScaleImage));
        }

        $fontObject->drawImageString($gdScaleImage, $scaleLeft, $scaleBottom + $tileHeight * 2 + 2, $title, $this->colourtable['KEYTEXT']->gdAllocate($gdScaleImage));

        for ($percentage = 0; $percentage <= 100; $percentage++) {
            $xOffset = $percentage * $scaleFactor;

            if (($percentage % 25) == 0) {
                imageline($gdScaleImage, $scaleLeft + $xOffset, $scaleTop - $tileHeight, $scaleLeft + $xOffset, $scaleBottom + $tileHeight, $this->colourtable['KEYTEXT']->gdAllocate($gdScaleImage));
                $labelString = sprintf("%d%%", $percentage);
                $fontObject->drawImageString($gdScaleImage, $scaleLeft + $xOffset + 2, $scaleTop - 2, $labelString, $this->colourtable['KEYTEXT']->gdAllocate($gdScaleImage));
            }

            list($col,) = $this->colourFromValue($percentage, $scaleName);
            if ($col->isRealColour()) {
                $cc = $col->gdAllocate($gdScaleImage);
                imagefilledrectangle($gdScaleImage, $scaleLeft + $xOffset - $scaleFactor / 2, $scaleTop, $scaleLeft + $xOffset + $scaleFactor / 2, $scaleBottom, $cc);
            }
        }


        // TODO: this should be in a different method - all drawLegendXXX should return a gdImage instead,
        //   and ONE place handles the drawing and imagemap
        imagecopy($gdTargetImage, $gdScaleImage, $this->keyx[$scaleName], $this->keyy[$scaleName], 0, 0, imagesx($gdScaleImage), imagesy($gdScaleImage));
        $this->keyimage[$scaleName] = $gdScaleImage;

        $xTarget = $this->keyx[$scaleName];
        $yTarget = $this->keyy[$scaleName];

        $areaName = "LEGEND:" . $scaleName;
        $this->imap->addArea("Rectangle", $areaName, '', array($xTarget + $boxLeft, $yTarget + $boxTop, $xTarget + $boxRight, $yTarget + $boxBottom));
        // TODO: stop tracking z-order seperately. addArea() should take the z layer
        $this->imap_areas[] = $areaName;

    }

    private function drawLegendVertical($im, $scalename = "DEFAULT", $height = 400, $inverted = false)
    {
        $title = $this->keytext[$scalename];

        # $colours=$this->colours[$scalename];
        $nscales = $this->numscales[$scalename];

        wm_debug("Drawing $nscales colours into SCALE\n");

        $font = $this->keyfont;

        $scalefactor = $height / 100;

        list($tilewidth, $tileheight) = $this->myimagestringsize($font, "100%");

        $box_left = 0;
        $box_top = 0;

        $scale_left = $box_left + $scalefactor * 2 + 4;
        $scale_right = $scale_left + $tileheight * 2;
        $box_right = $scale_right + $tilewidth + $scalefactor * 2 + 4;

        list($titlewidth,) = $this->myimagestringsize($font, $title);
        if (($box_left + $titlewidth + $scalefactor * 3) > $box_right) {
            $box_right = $box_left + $scalefactor * 4 + $titlewidth;
        }

        $scale_top = $box_top + 4 + $scalefactor + $tileheight * 2;
        $scale_bottom = $scale_top + $height;
        $box_bottom = $scale_bottom + $scalefactor + $tileheight / 2 + 4;

        $gdScaleImage = imagecreatetruecolor($box_right + 1, $box_bottom + 1);
        $scale_ref = 'gdref_legend_' . $scalename;

        // Start with a transparent box, in case the fill or outline colour is 'none'
        imageSaveAlpha($gdScaleImage, true);
        $nothing = imagecolorallocatealpha($gdScaleImage, 128, 0, 0, 127);
        imagefill($gdScaleImage, 0, 0, $nothing);

        $this->preAllocateScaleColours($gdScaleImage, $scale_ref);

        $bgColour = $this->colourtable['KEYBG'];
        $outlineColour = $this->colourtable['KEYOUTLINE'];

        wm_debug("BG is $bgColour, Outline is $outlineColour\n");

        if ($bgColour->isRealColour()) {
            imagefilledrectangle($gdScaleImage, $box_left, $box_top, $box_right, $box_bottom, $bgColour->gdAllocate($gdScaleImage));
        }

        if ($outlineColour->isRealColour()) {
            imagerectangle($gdScaleImage, $box_left, $box_top, $box_right, $box_bottom, $outlineColour->gdAllocate($gdScaleImage));
        }

        $this->myimagestring($gdScaleImage, $font, $scale_left - $scalefactor, $scale_top - $tileheight, $title, $this->colourtable['KEYTEXT']->gdAllocate($gdScaleImage));

        for ($p = 0; $p <= 100; $p++) {
            if ($inverted) {
                $delta_y = (100 - $p) * $scalefactor;
            } else {
                $delta_y = $p * $scalefactor;
            }

            if (($p % 25) == 0) {
                imageline($gdScaleImage, $scale_left - $scalefactor, $scale_top + $delta_y, $scale_right + $scalefactor, $scale_top + $delta_y, $this->colourtable['KEYTEXT']->gdAllocate($gdScaleImage));
                $labelstring = sprintf("%d%%", $p);
                $this->myimagestring($gdScaleImage, $font, $scale_right + $scalefactor * 2, $scale_top + $delta_y + $tileheight / 2, $labelstring, $this->colourtable['KEYTEXT']->gdAllocate($gdScaleImage));
            }

            list($col,) = $this->colourFromValue($p, $scalename);
            if ($col->isRealColour()) {
                imagefilledrectangle($gdScaleImage, $scale_left, $scale_top + $delta_y - $scalefactor / 2, $scale_right, $scale_top + $delta_y + $scalefactor / 2, $col->gdAllocate($gdScaleImage));
            }
        }

        imagecopy($im, $gdScaleImage, $this->keyx[$scalename], $this->keyy[$scalename], 0, 0, imagesx($gdScaleImage), imagesy($gdScaleImage));
        $this->keyimage[$scalename] = $gdScaleImage;

        $origin_x = $this->keyx[$scalename];
        $origin_y = $this->keyy[$scalename];

        $areaname = "LEGEND:$scalename";
        $this->imap->addArea("Rectangle", $areaname, '', array($origin_x + $box_left, $origin_y + $box_top, $origin_x + $box_right, $origin_y + $box_bottom));
        $this->imap_areas[] = $areaname;
    }

    private function drawLegendClassic($im, $scaleName = "DEFAULT", $useTags = false)
    {
        $title = $this->keytext[$scaleName];

        $colours = $this->colours[$scaleName];
        usort($colours, array("Weathermap", "scaleEntrySort"));

        $nscales = $this->numscales[$scaleName];

        wm_debug("Drawing $nscales colours into SCALE\n");

        $hide_zero = intval($this->get_hint("key_hidezero_" . $scaleName));
        $hide_percent = intval($this->get_hint("key_hidepercent_" . $scaleName));

        // did we actually hide anything?
        $hid_zero = false;
        if (($hide_zero == 1) && isset($colours['0_0'])) {
            $nscales--;
            $hid_zero = true;
        }

        $font = $this->keyfont;

        $x = $this->keyx[$scaleName];
        $y = $this->keyy[$scaleName];

        list($tileWidth, $tileHeight) = $this->myimagestringsize($font, "MMMM");
        $tileHeight = $tileHeight * 1.1;
        $tileSpacing = $tileHeight + 2;

        if (($this->keyx[$scaleName] >= 0) && ($this->keyy[$scaleName] >= 0)) {
            list($minwidth,) = $this->myimagestringsize($font, 'MMMM 100%-100%');
            list($minminwidth,) = $this->myimagestringsize($font, 'MMMM ');
            list($boxwidth,) = $this->myimagestringsize($font, $title);

            if ($useTags) {
                $max_tag = 0;
                foreach ($colours as $colour) {
                    if (isset($colour['tag'])) {
                        list($w,) = $this->myimagestringsize($font, $colour['tag']);
                        if ($w > $max_tag) {
                            $max_tag = $w;
                        }
                    }
                }

                // now we can tweak the widths, appropriately to allow for the tag strings
                if (($max_tag + $minminwidth) > $minwidth) {
                    $minwidth = $minminwidth + $max_tag;
                }
            }

            $minwidth += 10;
            $boxwidth += 10;

            if ($boxwidth < $minwidth) {
                $boxwidth = $minwidth;
            }

            $boxheight = $tileSpacing * ($nscales + 1) + 10;

            $boxx = 0;
            $boxy = 0;

            wm_debug("Scale Box is %dx%d\n", $boxwidth + 1, $boxheight + 1);

            $gdScaleImage = imagecreatetruecolor($boxwidth + 1, $boxheight + 1);

            // Start with a transparent box, in case the fill or outline colour is 'none'
            imageSaveAlpha($gdScaleImage, true);
            $nothing = imagecolorallocatealpha($gdScaleImage, 128, 0, 0, 127);
            imagefill($gdScaleImage, 0, 0, $nothing);

            $scale_ref = 'gdref_legend_' . $scaleName;
            $this->preAllocateScaleColours($gdScaleImage, $scale_ref);

            $bgColour = $this->colourtable['KEYBG'];
            $outlineColour = $this->colourtable['KEYOUTLINE'];

            if ($bgColour->isRealColour()) {
                imagefilledrectangle($gdScaleImage, $boxx, $boxy, $boxx + $boxwidth, $boxy + $boxheight, $bgColour->gdAllocate($gdScaleImage));
            }

            if ($outlineColour->isRealColour()) {
                imagerectangle($gdScaleImage, $boxx, $boxy, $boxx + $boxwidth, $boxy + $boxheight, $outlineColour->gdAllocate($gdScaleImage));
            }

            $this->myimagestring($gdScaleImage, $font, $boxx + 4, $boxy + 4 + $tileHeight, $title, $this->colourtable['KEYTEXT']->gdAllocate($gdScaleImage));

            $i = 1;

            foreach ($colours as $colour) {
                if (!isset($colour['special']) || $colour['special'] == 0) {
                    // pick a value in the middle...
                    $value = ($colour['bottom'] + $colour['top']) / 2;
                    wm_debug(sprintf("%f-%f (%f)  %s\n", $colour['bottom'], $colour['top'], $value, $colour['c1']));

                    #  debug("$i: drawing\n");
                    if (($hide_zero == 0) || $colour['key'] != '0_0') {
                        $y = $boxy + $tileSpacing * $i + 8;
                        $x = $boxx + 6;

                        $fudgefactor = 0;
                        if ($hid_zero && $colour['bottom'] == 0) {
                            // calculate a small offset that can be added, which will hide the zero-value in a
                            // gradient, but not make the scale incorrect. A quarter of a pixel should do it.
                            $fudgefactor = ($colour['top'] - $colour['bottom']) / ($tileWidth * 4);
                        }

                        // if it's a gradient, red2 is defined, and we need to sweep the values
                        if (isset($colour['c2'])) {
                            for ($n = 0; $n <= $tileWidth; $n++) {
                                $value = $fudgefactor + $colour['bottom'] + ($n / $tileWidth) * ($colour['top'] - $colour['bottom']);
                                list($ccol,) = $this->colourFromValue($value, $scaleName, "", false);
                                $col = $ccol->gdallocate($gdScaleImage);
                                imagefilledrectangle($gdScaleImage, $x + $n, $y, $x + $n, $y + $tileHeight, $col);
                            }
                        } else {
                            // pick a value in the middle...
                            list($ccol,) = $this->colourFromValue($value, $scaleName, "", false);
                            $col = $ccol->gdallocate($gdScaleImage);
                            imagefilledrectangle($gdScaleImage, $x, $y, $x + $tileWidth, $y + $tileHeight, $col);
                        }

                        if ($useTags) {
                            $labelstring = "";
                            if (isset($colour['tag'])) {
                                $labelstring = $colour['tag'];
                            }
                        } else {
                            $labelstring = sprintf("%s-%s", $colour['bottom'], $colour['top']);
                            if ($hide_percent == 0) {
                                $labelstring .= "%";
                            }
                        }

                        $this->myimagestring($gdScaleImage, $font, $x + 4 + $tileWidth, $y + $tileHeight, $labelstring, $this->colourtable['KEYTEXT']->gdAllocate($gdScaleImage));
                        $i++;
                    }
                }
            }

            imagecopy($im, $gdScaleImage, $this->keyx[$scaleName], $this->keyy[$scaleName], 0, 0, imagesx($gdScaleImage), imagesy($gdScaleImage));
            $this->keyimage[$scaleName] = $gdScaleImage;

            $areaname = "LEGEND:$scaleName";

            $this->imap->addArea("Rectangle", $areaname, '', array($this->keyx[$scaleName], $this->keyy[$scaleName], $this->keyx[$scaleName] + $boxwidth, $this->keyy[$scaleName] + $boxheight));
            $this->imap_areas[] = $areaname;
        }
    }

    private function drawTimestamp($gdImage, $fontNumber, $colour, $which = "")
    {
        // add a timestamp to the corner, so we can tell if it's all being updated
        $fontObject = $this->fonts->getFont($fontNumber);

        switch ($which) {
            case "MIN":
                $stamp = strftime($this->minstamptext, $this->min_data_time);
                $pos_x = $this->mintimex;
                $pos_y = $this->mintimey;
                break;
            case "MAX":
                $stamp = strftime($this->maxstamptext, $this->max_data_time);
                $pos_x = $this->maxtimex;
                $pos_y = $this->maxtimey;
                break;
            default:
                $stamp = $this->datestamp;
                $pos_x = $this->timex;
                $pos_y = $this->timey;
                break;
        }

        list($boxwidth, $boxheight) = $fontObject->calculateImageStringSize($stamp);

        $x = $this->width - $boxwidth;
        $y = $boxheight;

        if (($pos_x != 0) && ($pos_y != 0)) {
            $x = $pos_x;
            $y = $pos_y;
        }

        $areaname = $which . "TIMESTAMP";
        $fontObject->drawImageString($gdImage, $x, $y, $stamp, $colour);

        $this->imap->addArea("Rectangle", $areaname, '', array($x, $y, $x + $boxwidth, $y - $boxheight));
        $this->imap_areas[] = $areaname;
    }

    private function drawTitle($gdImage, $fontNumber, $colour)
    {
        $string = $this->processString($this->title, $this);
        $fontObject = $this->fonts->getFont($fontNumber);

        if ($this->get_hint('screenshot_mode') == 1) {
            $string = wmStringAnonymise($string);
        }

        list($boxwidth, $boxheight) = $fontObject->calculateImageStringSize($string);

        $x = 10;
        $y = $this->titley - $boxheight;

        if (($this->titlex >= 0) && ($this->titley >= 0)) {
            $x = $this->titlex;
            $y = $this->titley;
        }

        $fontObject->drawImageString($gdImage, $x, $y, $string, $colour);

        $this->imap->addArea("Rectangle", "TITLE", '', array($x, $y, $x + $boxwidth, $y - $boxheight));
        $this->imap_areas[] = 'TITLE';
    }

    /**
     * ReadConfig reads in either a file or part of a config and modifies the current map.
     *
     * Temporary new ReadConfig, using the new table of keywords
     * However, this also expects a bunch of other internal change (WMColour, WMScale etc)
     *
     * @param $input string Either a filename or a fragment of config in a string
     * @return bool indicates success or failure     *
     *
     */
    function ReadConfig($input)
    {
        $reader = new WeatherMapConfigReader();
        $reader->Init($this);

        // check if $input is more than one line. if it is, it's a text of a config file
        // if it isn't, it's the filename

        if ((strchr($input, "\n") != false) || (strchr($input, "\r") != false)) {
            wm_debug("ReadConfig Detected that this is a config fragment.\n");
            // strip out any Windows line-endings that have gotten in here
            $input = str_replace("\r", "", $input);
            $lines = explode("\n", $input);
            $filename = "{text insert}";

            $reader->readConfigLines($lines);

        } else {
            wm_debug("ReadConfig Detected that this is a config filename.\n");
            $reader->readConfigFile($input);
            $this->configfile = $input;
        }

        $this->postReadConfigTasks();

        return (true);
    }

    function postReadConfigTasks()
    {
        if ($this->has_overlibs && $this->htmlstyle == 'static') {
            wm_warn("OVERLIBGRAPH is used, but HTMLSTYLE is static. This is probably wrong. [WMWARN41]\n");
        }

        $this->populateDefaultScales();
        $this->replicateScaleSettings();
        $this->buildZLayers();
        $this->resolveRelativePositions();
        $this->runProcessorPlugins("pre");
    }

    // *************************************

    private function populateDefaultScales()
    {
        // load some default colouring, otherwise it all goes wrong

        wm_debug("Hello!\n");

        $count = $this->scales['DEFAULT']->spanCount();

        $this->scales['DEFAULT']->populateDefaultsIfNecessary();

        if ($count == 0) {
            wm_debug("Adding default SCALE colour set (no SCALE lines seen).\n");
            $defaults = array(
                '0_0' => array(
                    'bottom' => 0,
                    'top' => 0,
                    'red1' => 192,
                    'green1' => 192,
                    'blue1' => 192,
                    'special' => 0
                ),
                '0_1' => array(
                    'bottom' => 0,
                    'top' => 1,
                    'red1' => 255,
                    'green1' => 255,
                    'blue1' => 255,
                    'special' => 0
                ),
                '1_10' => array(
                    'bottom' => 1,
                    'top' => 10,
                    'red1' => 140,
                    'green1' => 0,
                    'blue1' => 255,
                    'special' => 0
                ),
                '10_25' => array(
                    'bottom' => 10,
                    'top' => 25,
                    'red1' => 32,
                    'green1' => 32,
                    'blue1' => 255,
                    'special' => 0
                ),
                '25_40' => array(
                    'bottom' => 25,
                    'top' => 40,
                    'red1' => 0,
                    'green1' => 192,
                    'blue1' => 255,
                    'special' => 0
                ),
                '40_55' => array(
                    'bottom' => 40,
                    'top' => 55,
                    'red1' => 0,
                    'green1' => 240,
                    'blue1' => 0,
                    'special' => 0
                ),
                '55_70' => array(
                    'bottom' => 55,
                    'top' => 70,
                    'red1' => 240,
                    'green1' => 240,
                    'blue1' => 0,
                    'special' => 0
                ),
                '70_85' => array(
                    'bottom' => 70,
                    'top' => 85,
                    'red1' => 255,
                    'green1' => 192,
                    'blue1' => 0,
                    'special' => 0
                ),
                '85_100' => array(
                    'bottom' => 85,
                    'top' => 100,
                    'red1' => 255,
                    'green1' => 0,
                    'blue1' => 0,
                    'special' => 0
                )
            );

            foreach ($defaults as $key => $def) {
                $this->colours['DEFAULT'][$key] = $def;
                $this->colours['DEFAULT'][$key]['key'] = $key;
                $this->colours['DEFAULT'][$key]['c1'] = new WMColour($def['red1'], $def['green1'], $def['blue1']);
                $this->colours['DEFAULT'][$key]['c2'] = null;
                // $this->scalesseen++;
                $this->numscales['DEFAULT']++;
            }
            // we have a 0-0 line now, so we need to hide that.
            $this->add_hint("key_hidezero_DEFAULT", 1);
        } else {
            wm_debug("Already have %d scales, no defaults added.\n", $this->scales['DEFAULT']->spanCount());
        }
    }

    /**
     * Temporary function to bridge between the old and new
     * scale-worlds. Just until the ConfigReader updates these
     * directly.
     */
    private function replicateScaleSettings()
    {
        foreach ($this->scales as $name=>$scaleObject) {

            $scaleObject->keypos = new WMPoint($this->keyx[$name], $this->keyy[$name]);
            $scaleObject->keystyle = $this->keystyle[$name];
            $scaleObject->keytitle = $this->keytext[$name];

            $scaleObject->keyoutlinecolour = $this->colourtable['KEYOUTLINE'];
            $scaleObject->keytextcolour = $this->colourtable['KEYTEXT'];
            $scaleObject->keybgcolour = $this->colourtable['KEYBG'];
        }
    }

    private function buildZLayers()
    {
        wm_debug("Building cache of z-layers and finalising bandwidth.\n");

        $allItems = $this->buildAllItemsList();

        foreach ($allItems as $item) {
            $z = $item->zorder;
            if (!isset($this->seen_zlayers[$z]) || !is_array($this->seen_zlayers[$z])) {
                $this->seen_zlayers[$z] = array();
            }
            array_push($this->seen_zlayers[$z], $item);

            // while we're looping through, let's set the real bandwidths
            $item->maxValues[IN] = wmInterpretNumberWithMetricPrefix($item->max_bandwidth_in_cfg, $this->kilo);
            $item->maxValues[OUT] = wmInterpretNumberWithMetricPrefix($item->max_bandwidth_out_cfg, $this->kilo);

            // TODO - remove PHP4ism
            if ($item->my_type() == "LINK") {
                $this->links[$item->name]->max_bandwidth_in = wmInterpretNumberWithMetricPrefix($item->max_bandwidth_in_cfg, $this->kilo);
                $this->links[$item->name]->max_bandwidth_out = wmInterpretNumberWithMetricPrefix($item->max_bandwidth_out_cfg, $this->kilo);
            } elseif ($item->my_type() == "NODE") {
                $this->nodes[$item->name]->max_bandwidth_in = wmInterpretNumberWithMetricPrefix($item->max_bandwidth_in_cfg, $this->kilo);
                $this->nodes[$item->name]->max_bandwidth_out = wmInterpretNumberWithMetricPrefix($item->max_bandwidth_out_cfg, $this->kilo);
            } else {
                wm_warn("Internal bug - found an item of type: " . $item->my_type() . "\n");
            }

            wm_debug(sprintf("   Setting bandwidth on " . $item->my_type() . " $item->name (%s -> %d bps, %s -> %d bps, KILO = %d)\n", $item->max_bandwidth_in_cfg, $item->max_bandwidth_in, $item->max_bandwidth_out_cfg, $item->max_bandwidth_out, $this->kilo));
        }
        wm_debug("Found " . sizeof($this->seen_zlayers) . " z-layers including builtins (0,100).\n");
    }

    private function runProcessorPlugins($stage = "pre")
    {
        wm_debug("Running $stage-processing plugins...\n");
        foreach ($this->plugins[$stage] as $name => $pluginEntry) {
            wm_debug("Running %s->run()\n", $name);
            $pluginEntry['object']->run($this);
        }
        wm_debug("Finished $stage-processing plugins...\n");
    }

    private function resolveRelativePositions()
    {
        // calculate any relative positions here - that way, nothing else
        // really needs to know about them

        wm_debug("Resolving relative positions for NODEs...\n");
        // safety net for cyclic dependencies
        $i = 100;
        do {
            $skipped = 0;
            $set = 0;
            foreach ($this->nodes as $node) {
                if (($node->relative_to != '') && (!$node->relative_resolved)) {
                    wm_debug("Resolving relative position for NODE " . $node->name . " to " . $node->relative_to . "\n");
                    if (array_key_exists($node->relative_to, $this->nodes)) {
                        // check if we are relative to another node which is in turn relative to something
                        // we need to resolve that one before we can resolve this one!
                        if (($this->nodes[$node->relative_to]->relative_to != '') && (!$this->nodes[$node->relative_to]->relative_resolved)) {
                            wm_debug("Skipping unresolved relative_to. Let's hope it's not a circular one\n");
                            $skipped++;
                        } else {
                            $rx = $this->nodes[$node->relative_to]->x;
                            $ry = $this->nodes[$node->relative_to]->y;

                            if ($node->polar) {
                                // treat this one as a POLAR relative coordinate.
                                // - draw rings around a node!
                                $angle = $node->x;
                                $distance = $node->y;
                                $newpos_x = $rx + $distance * sin(deg2rad($angle));
                                $newpos_y = $ry - $distance * cos(deg2rad($angle));
                                wm_debug("->$newpos_x,$newpos_y\n");
                                $this->nodes[$node->name]->x = $newpos_x;
                                $this->nodes[$node->name]->y = $newpos_y;
                                $this->nodes[$node->name]->relative_resolved = true;
                                $set++;
                            } elseif ($node->pos_named) {
                                $off_name = $node->relative_name;
                                if (isset($this->nodes[$node->relative_to]->named_offsets[$off_name])) {
                                    $this->nodes[$node->name]->x = $rx + $this->nodes[$node->relative_to]->named_offsets[$off_name][0];
                                    $this->nodes[$node->name]->y = $ry + $this->nodes[$node->relative_to]->named_offsets[$off_name][1];
                                } else {
                                    $skipped++;
                                }
                            } else {
                                // save the relative coords, so that WriteConfig can work
                                // resolve the relative stuff

                                $newpos_x = $rx + $this->nodes[$node->name]->x;
                                $newpos_y = $ry + $this->nodes[$node->name]->y;
                                wm_debug("->$newpos_x,$newpos_y\n");
                                $this->nodes[$node->name]->x = $newpos_x;
                                $this->nodes[$node->name]->y = $newpos_y;
                                $this->nodes[$node->name]->relative_resolved = true;
                                $set++;
                            }
                        }
                    } else {
                        wm_warn("NODE " . $node->name . " has a relative position to an unknown node (" . $node->relative_to . ")! [WMWARN10]\n");
                    }
                }
            }
            wm_debug("Relative Positions Cycle $i - set $set and Skipped $skipped for unresolved dependencies\n");
            $i--;
        } while (($set > 0) && ($i != 0));

        if ($skipped > 0) {
            wm_warn("There are Circular dependencies in relative POSITION lines for $skipped nodes. [WMWARN11]\n");
        }

        wm_debug("-----------------------------------\n");

    }

    function writeJSONFile($filename)
    {

        if ($filename != "") {
            $fd = fopen($filename, 'w');
            # $output = '';

            if ($fd) {
                $data = array();
                $data['nodes'] = array();
                $data['links'] = array();
                $data['scales'] = array();

                foreach ($this->scales as $scale) {
                    $data['scales'][$scale->name] = array();
                }

                foreach ($this->nodes as $node) {
                    $data['nodes'][$node->name] = array();

                    $data['nodes'][$node->name]["x"] = $node->x;
                    $data['nodes'][$node->name]["y"] = $node->y;

                    $data['nodes'][$node->name]["bounding_boxes"] = array();
                    foreach ($node->boundingboxes as $bb) {
                        $data['nodes'][$node->name]["bounding_boxes"][] = array($bb[0], $bb[1], $bb[2], $bb[3]);
                    }

                    $data['nodes'][$node->name]["template"] = false;
                    if ($node->x === null) {
                        $data['nodes'][$node->name]["template"] = true;
                    }
                }

                foreach ($this->links as $link) {
                    $data['links'][$link->name] = array();
                    $data['links'][$link->name]["width"] = $link->width;
                    $data['links'][$link->name]["nodes"] = array($link->a->name, $link->b->name);

                    $data['links'][$link->name]["template"] = false;
                    if ($link->a === null) {
                        $data['links'][$link->name]["template"] = true;
                    }
                }

                fputs($fd, json_encode($data, JSON_PRETTY_PRINT));

            }
        }
    }

    function writeDataFile($filename)
    {
        if ($filename != "") {
            $fd = fopen($filename, 'w');

            if ($fd) {
                foreach ($this->nodes as $node) {
                    if (!preg_match("/^::\s/", $node->name) && sizeof($node->targets) > 0) {
                        fputs($fd, sprintf("N_%s\t%f\t%f\r\n", $node->name, $node->bandwidth_in, $node->bandwidth_out));
                    }
                }

                foreach ($this->links as $link) {
                    if (!preg_match("/^::\s/", $link->name) && sizeof($link->targets) > 0) {
                        fputs($fd, sprintf("L_%s\t%f\t%f\r\n", $link->name, $link->bandwidth_in, $link->bandwidth_out));
                    }
                }

                fclose($fd);
            }
        }
    }

    function getConfig()
    {
        global $WEATHERMAP_VERSION;

        $output = "# Automatically generated by php-weathermap v$WEATHERMAP_VERSION\n\n";

        $output .= $this->fonts->getConfig();

        $basic_params = array(
            array('background','BACKGROUND',CONFIG_TYPE_LITERAL),
            array('width','WIDTH',CONFIG_TYPE_LITERAL),
            array('height','HEIGHT',CONFIG_TYPE_LITERAL),
            array('htmlstyle','HTMLSTYLE',CONFIG_TYPE_LITERAL),
            array('kilo','KILO',CONFIG_TYPE_LITERAL),
            array('keyfont','KEYFONT',CONFIG_TYPE_LITERAL),
            array('timefont','TIMEFONT',CONFIG_TYPE_LITERAL),
            array('titlefont','TITLEFONT',CONFIG_TYPE_LITERAL),
            array('title','TITLE',CONFIG_TYPE_LITERAL),
            array('htmloutputfile','HTMLOUTPUTFILE',CONFIG_TYPE_LITERAL),
            array('dataoutputfile','DATAOUTPUTFILE',CONFIG_TYPE_LITERAL),
            array('htmlstylesheet','HTMLSTYLESHEET',CONFIG_TYPE_LITERAL),
            array('imageuri','IMAGEURI',CONFIG_TYPE_LITERAL),
            array('imageoutputfile','IMAGEOUTPUTFILE',CONFIG_TYPE_LITERAL)
        );

        foreach ($basic_params as $param) {
            $field = $param[0];
            $keyword = $param[1];

            if ($this->$field != $this->inherit_fieldlist[$field]) {
                if ($param[2] == CONFIG_TYPE_COLOR) {
                    $output.="$keyword " . render_colour($this->$field) . "\n";
                }
                if ($param[2] == CONFIG_TYPE_LITERAL) {
                    $output.="$keyword " . $this->$field . "\n";
                }
            }
        }

        if (($this->timex != $this->inherit_fieldlist['timex'])
            || ($this->timey != $this->inherit_fieldlist['timey'])
            || ($this->stamptext != $this->inherit_fieldlist['stamptext'])) {
            $output .= "TIMEPOS " . $this->timex . " " . $this->timey . " " . $this->stamptext . "\n";
        }

        if (($this->mintimex != $this->inherit_fieldlist['mintimex'])
            || ($this->mintimey != $this->inherit_fieldlist['mintimey'])
            || ($this->minstamptext != $this->inherit_fieldlist['minstamptext'])) {
            $output .= "MINTIMEPOS " . $this->mintimex . " " . $this->mintimey . " " . $this->minstamptext . "\n";
        }

        if (($this->maxtimex != $this->inherit_fieldlist['maxtimex'])
            || ($this->maxtimey != $this->inherit_fieldlist['maxtimey'])
            || ($this->maxstamptext != $this->inherit_fieldlist['maxstamptext'])) {
            $output .= "MAXTIMEPOS " . $this->maxtimex . " " . $this->maxtimey . " " . $this->maxstamptext . "\n";
        }

        if (($this->titlex != $this->inherit_fieldlist['titlex'])
            || ($this->titley != $this->inherit_fieldlist['titley'])) {
            $output .= "TITLEPOS " . $this->titlex . " " . $this->titley . "\n";
        }

        $output.="\n";

        foreach ($this->colours as $scalename => $colours) {
            // not all keys will have keypos but if they do, then all three vars should be defined
            if ((isset($this->keyx[$scalename])) && (isset($this->keyy[$scalename])) && (isset($this->keytext[$scalename]))
                && (($this->keytext[$scalename] != $this->inherit_fieldlist['keytext'])
                    || ($this->keyx[$scalename] != $this->inherit_fieldlist['keyx'])
                    || ($this->keyy[$scalename] != $this->inherit_fieldlist['keyy']))) {
                // sometimes a scale exists but without defaults. A proper scale object would sort this out...
                if ($this->keyx[$scalename] == '') {
                    $this->keyx[$scalename] = -1;
                }
                if ($this->keyy[$scalename] == '') {
                    $this->keyy[$scalename] = -1;
                }
                $output.="KEYPOS " . $scalename." ". $this->keyx[$scalename] . " " . $this->keyy[$scalename] . " " . $this->keytext[$scalename] . "\n";
            }

            if ((isset($this->keystyle[$scalename])) &&  ($this->keystyle[$scalename] != $this->inherit_fieldlist['keystyle']['DEFAULT'])) {
                $extra='';
                if ((isset($this->keysize[$scalename])) &&  ($this->keysize[$scalename] != $this->inherit_fieldlist['keysize']['DEFAULT'])) {
                    $extra = " ".$this->keysize[$scalename];
                }
                $output.="KEYSTYLE  " . $scalename." ". $this->keystyle[$scalename] . $extra . "\n";
            }
            $locale = localeconv();
            $decimal_point = $locale['decimal_point'];

            // Disable the old scale-output stuff for now
            if (1==0) {
                foreach ($colours as $k => $colour) {
                    if (!isset($colour['special']) || !$colour['special']) {
                        $top = rtrim(rtrim(sprintf("%f", $colour['top']), "0"), $decimal_point);
                        $bottom = rtrim(rtrim(sprintf("%f", $colour['bottom']), "0"), $decimal_point);

                        if ($bottom > 1000) {
                            $bottom = wmFormatNumberWithMetricPrefix($colour['bottom'], $this->kilo);
                        }

                        if ($top > 1000) {
                            $top = wmFormatNumberWithMetricPrefix($colour['top'], $this->kilo);
                        }

                        $tag = (isset($colour['tag']) ? $colour['tag'] : '');

                        if (($colour['c1']->isNone())) {
                            $output .= sprintf("SCALE %s %-4s %-4s   none   %s\n", $scalename, $bottom, $top, $tag);
                        } elseif (!isset($colour['c2'])) {
                            $output .= sprintf(
                                "SCALE %s %-4s %-4s %s  %s\n",
                                $scalename,
                                $bottom,
                                $top,
                                $colour['c1']->asConfig(),
                                $tag
                            );
                        } else {
                            $output .= sprintf(
                                "SCALE %s %-4s %-4s %s   %s    %s\n",
                                $scalename,
                                $bottom,
                                $top,
                                $colour['c1']->asConfig(),
                                $colour['c2']->asConfig(),
                                $tag
                            );
                        }
                    }
                }
                $output .= "\n";
            }
        }

        if (1==1) {
            // TODO - These should replace the stuff above
            $output .= "# new colourtable stuff (duplicated above right now TODO)\n";
            foreach ($this->colourtable as $k => $c) {
                $output .= sprintf("%sCOLOR %s\n", $k, $c->asConfig());
            }
            $output .= "\n";

            foreach ($this->scales as $s) {
                $output .= $s->getConfig();
            }

            $output .= "\n";
        }

        foreach ($this->hints as $hintname => $hint) {
            $output .= "SET $hintname $hint\n";
        }

        // this doesn't really work right, but let's try anyway
        if ($this->has_includes) {
            $output .= "\n# Included files\n";
            foreach ($this->included_files as $ifile) {
                $output .= "INCLUDE $ifile\n";
            }
        }

        $output.="\n# End of global section\n\n";

        foreach (array("template","normal") as $which) {
            if ($which == "template") {
                $output .= "\n# TEMPLATE-only NODEs:\n";
            }
            if ($which == "normal") {
                $output .= "\n# regular NODEs:\n";
            }

            foreach ($this->nodes as $node) {
                if (!preg_match("/^::\s/", $node->name)) {
                    wm_debug("WriteConfig: Considering Node %s defined in %s\n", $node->name, $node->getDefined());
                    if ($node->getDefined() == $this->configfile) {
                        if ($which=="template" && $node->x === null) {
                            wm_debug("TEMPLATE\n");
                            $output .= $node->getConfig();
                        }
                        if ($which=="normal" && $node->x !== null) {
                            $output .= $node->getConfig();
                        }
                    } else {
                        wm_debug("Not writing Node $node->name - defined in another file\n");
                    }
                }
            }

            if ($which == "template") {
                $output .= "\n# TEMPLATE-only LINKs:\n";
            }
            if ($which == "normal") {
                $output .= "\n# regular LINKs:\n";
            }

            foreach ($this->links as $link) {
                if (!preg_match("/^::\s/", $link->name)) {
                    wm_debug("WriteConfig: Considering Link %s defined in %s\n", $link->name, $link->getDefined());
                    if ($link->getDefined() == $this->configfile) {
                        if ($which=="template" && $link->a === null) {
                            $output .= $link->getConfig();
                        }
                        if ($which=="normal" && $link->a !== null) {
                            $output .= $link->getConfig();
                        }
                    }
                }
            }
        }

        $output .= "\n\n# That's All Folks!\n";

        return $output;
    }

    function writeConfig($filename)
    {
        wm_debug("Writing config to $filename (read from $this->configfile)\n");

        $fd=fopen($filename, "w");

        if ($fd) {
            $output = $this->getConfig();
            fwrite($fd, $output);
            fclose($fd);
        } else {
            wm_warn("Couldn't open config file $filename for writing");
            return (false);
        }

        return (true);
    }

    // pre-allocate colour slots for the colours used by the arrows
    // this way, it's the pretty icons that suffer if there aren't enough colours, and
    // not the actual useful data
    // we skip any gradient scales
    private function preAllocateScaleColours($im, $refname = 'gdref1')
    {
        foreach ($this->colours as $scalename => $colours) {
            foreach ($colours as $key => $colour) {
                if ((!isset($this->colours[$scalename][$key]['c2'])) && (!isset($this->colours[$scalename][$key][$refname]))) {
                    $this->colours[$scalename][$key][$refname] = $this->colours[$scalename][$key]['c1']->gdAllocate($im);
                    wm_debug("AllocateScaleColours: %s/%s %s %s\n", $scalename, $refname, $key, $this->colours[$scalename][$key]['c1']);
                }
            }
        }
    }

    function drawMapImage($filename = '', $thumbnailfile = '', $thumbnailmax = 250, $withnodes = true, $use_via_overlay = false, $use_rel_overlay = false)
    {
        wm_debug("Trace: DrawMap()\n");

        $bgimage=null;

        wm_debug("Running Post-Processing Plugins...\n");
        foreach ($this->plugins['post'] as $pluginName => $pluginEntry) {
            wm_debug("Running $pluginName"."->run()\n");
            $pluginEntry['object']->run($this);
        }
        wm_debug("Finished Post-Processing Plugins...\n");

        wm_debug("=====================================\n");
        wm_debug("Start of Map Drawing\n");

        // if we're running tests, we force the time to a particular value,
        // so the output can be compared to a reference image more easily
        $testmode = intval($this->get_hint("testmode"));

        if ($testmode == 1) {
            $maptime = 1270813792;
            date_default_timezone_set('UTC');
        } else {
            $maptime = time();
        }

        $this->datestamp = strftime($this->stamptext, $maptime);

        // do the basic prep work
        if ($this->background != '') {
            if (is_readable($this->background)) {
                $bgimage=imagecreatefromfile($this->background);

                if (!$bgimage) {
                    wm_warn("Failed to open background image.  One possible reason: Is your BACKGROUND really a PNG?\n");
                } else {
                    $this->width=imagesx($bgimage);
                    $this->height=imagesy($bgimage);
                }
            } else {
                wm_warn("Your background image file could not be read. Check the filename, and permissions, for " . $this->background . "\n");
            }
        }

        $image=imagecreatetruecolor($this->width, $this->height);

        if (!$image) {
            wm_warn("Couldn't create output image in memory (" . $this->width . "x" . $this->height . ").");
        } else {
            ImageAlphaBlending($image, true);

            if ($this->get_hint("antialias") == 1) {
                    // Turn on anti-aliasing if it exists and it was requested
                if (function_exists("imageantialias")) {
                    imageantialias($image, true);
                }
            }

            // by here, we should have a valid image handle

            // save this away, now
            $this->image=$image;

            $this->white=myimagecolorallocate($image, 255, 255, 255);
            $this->black=myimagecolorallocate($image, 0, 0, 0);
            $this->grey=myimagecolorallocate($image, 192, 192, 192);
            $this->selected=myimagecolorallocate($image, 255, 0, 0); // for selections in the editor

            $this->preAllocateScaleColours($image);

            if ($bgimage) {
                imagecopy($image, $bgimage, 0, 0, 0, 0, $this->width, $this->height);
                imagedestroy($bgimage);
            } else {
                // fill with background colour anyway, since the background image failed to load
                imagefilledrectangle($image, 0, 0, $this->width, $this->height, $this->colourtable['BG']->gdallocate($image));
            }

            // Now it's time to draw a map

            // do the node rendering stuff first, regardless of where they are actually drawn.
            // this is so we can get the size of the nodes, which links will need if they use offsets
            foreach ($this->nodes as $node) {
                // don't try and draw template nodes
                wm_debug("Pre-rendering ".$node->name." to get bounding boxes.\n");
                if (!is_null($node->x)) {
                    $this->nodes[$node->name]->preRender($image, $this);
                }
            }

            $all_layers = array_keys($this->seen_zlayers);
            sort($all_layers);

            foreach ($all_layers as $z) {
                $z_items = $this->seen_zlayers[$z];
                wm_debug("Drawing layer $z\n");
                // all the map 'furniture' is fixed at z=1000
                if ($z==1000) {
                    foreach ($this->scales as $scaleName => $scaleObject) {
                        wm_debug("Drawing KEY for $scaleName if necessary.\n");

                        if (1==1) {
                            if ((isset($this->numscales[$scaleName])) && (isset($this->keyx[$scaleName])) && ($this->keyx[$scaleName] >= 0) && ($this->keyy[$scaleName] >= 0)) {

                                if ($this->keystyle[$scaleName] == 'classic') {
                                    $this->drawLegendClassic($image, $scaleName, false);
                                }
                                if ($this->keystyle[$scaleName] == 'horizontal') {
                                    $this->drawLegendHorizontal($image, $scaleName, $this->keysize[$scaleName]);
                                }
                                if ($this->keystyle[$scaleName] == 'vertical') {
                                    $this->drawLegendVertical($image, $scaleName, $this->keysize[$scaleName]);
                                }
                                if ($this->keystyle[$scaleName] == 'inverted') {
                                    $this->drawLegendVertical($image, $scaleName, $this->keysize[$scaleName], true);
                                }
                                if ($this->keystyle[$scaleName] == 'tags') {
                                    $this->drawLegendClassic($image, $scaleName, true);
                                }
                            }
                        }

                        // the new scale object draws its own legend
                        $this->scales[$scaleName]->drawLegend($image);
                    }

                    $this->drawTimestamp($image, $this->timefont, $this->colourtable['TIME']->gdallocate($image));
                    if (! is_null($this->min_data_time)) {
                        $this->drawTimestamp($image, $this->timefont, $this->colourtable['TIME']->gdallocate($image), "MIN");
                        $this->drawTimestamp($image, $this->timefont, $this->colourtable['TIME']->gdallocate($image), "MAX");
                    }
                    $this->drawTitle($image, $this->titlefont, $this->colourtable['TITLE']->gdallocate($image));
                }

                if (is_array($z_items)) {
                    foreach ($z_items as $it) {
                        if (strtolower(get_class($it))=='weathermaplink') {
                            // only draw LINKs if they have NODES defined (not templates)
                            // (also, check if the link still exists - if this is in the editor, it may have been deleted by now)
                            if (isset($this->links[$it->name]) && isset($it->a) && isset($it->b)) {
                                wm_debug("Drawing LINK ".$it->name."\n");
                                $this->links[$it->name]->preChecks($this);
                                $this->links[$it->name]->preCalculate($this);
                                $this->links[$it->name]->draw($image, $this);
                            }
                        }
                        if (strtolower(get_class($it))=='weathermapnode') {
                            // if (!is_null($it->x)) $it->pre_render($image, $this);
                            if ($withnodes) {
                                // don't try and draw template nodes
                                if (isset($this->nodes[$it->name]) && !is_null($it->x)) {
                                    # print "::".get_class($it)."\n";
                                    wm_debug("Drawing NODE ".$it->name."\n");
                                    $this->nodes[$it->name]->Draw($image, $this);
                                    $ii=0;
                                    foreach ($this->nodes[$it->name]->boundingboxes as $bbox) {
                                        $areaname = "NODE:N". $it->id . ":" . $ii;
                                        $this->imap->addArea("Rectangle", $areaname, '', $bbox);
                                        wm_debug("Adding imagemap area");
                                        $this->nodes[$it->name]->imap_areas[] = $areaname;
                                        $ii++;
                                    }
                                    wm_debug("Added $ii bounding boxes too\n");
                                }
                            }
                        }
                    }
                }
            }

            $overlay = myimagecolorallocate($image, 200, 0, 0);

            // for the editor, we can optionally overlay some other stuff
            if ($this->context == 'editor') {
                if ($use_rel_overlay) {
                    // first, we can show relatively positioned NODEs
                    foreach ($this->nodes as $node) {
                        if ($node->relative_to != '') {
                            $rel_x = $this->nodes[$node->relative_to]->x;
                            $rel_y = $this->nodes[$node->relative_to]->y;
                            imagearc($image, $node->x, $node->y, 15, 15, 0, 360, $overlay);
                            imagearc($image, $node->x, $node->y, 16, 16, 0, 360, $overlay);

                            imageline($image, $node->x, $node->y, $rel_x, $rel_y, $overlay);
                        }
                    }
                }

                if ($use_via_overlay) {
                    // then overlay VIAs, so they can be seen
                    foreach ($this->links as $link) {
                        foreach ($link->vialist as $via) {
                            if (isset($via[2])) {
                                $x = $this->nodes[$via[2]]->x + $via[0];
                                $y = $this->nodes[$via[2]]->y + $via[1];
                            } else {
                                $x = $via[0];
                                $y = $via[1];
                            }
                            imagearc($image, $x, $y, 10, 10, 0, 360, $overlay);
                            imagearc($image, $x, $y, 12, 12, 0, 360, $overlay);
                        }
                    }

                    // also (temporarily) draw all named offsets for each node
                    foreach ($this->nodes as $node) {
                        if (!is_null($node->x)) {
                            foreach ($node->named_offsets as $offsets) {
                                $x = $node->x + $offsets[0];
                                $y = $node->y + $offsets[1];
                                imagearc($image, $x, $y, 4, 4, 0, 360, $overlay);
                            }
                        }
                    }
                }
            }

            // Ready to output the results...

            if ($filename == 'null') {
                // do nothing at all - we just wanted the HTML AREAs for the editor or HTML output
            } else {
                if ($filename == '') {
                    imagepng($image);
                } else {
                    $result = false;
                    $functions = true;
                    if (function_exists('imagejpeg') && preg_match("/\.jpg/i", $filename)) {
                        wm_debug("Writing JPEG file to $filename\n");
                        $result = imagejpeg($image, $filename);
                    } elseif (function_exists('imagegif') && preg_match("/\.gif/i", $filename)) {
                        wm_debug("Writing GIF file to $filename\n");
                        $result = imagegif($image, $filename);
                    } elseif (function_exists('imagepng') && preg_match("/\.png/i", $filename)) {
                        wm_debug("Writing PNG file to $filename\n");
                        $result = imagepng($image, $filename);
                    } else {
                        wm_warn("Failed to write map image. No function existed for the image format you requested ($filename). [WMWARN12]\n");
                        $functions = false;
                    }

                    if (($result===false) && ($functions===true)) {
                        if (file_exists($filename)) {
                            wm_warn("Failed to overwrite existing image file $filename - permissions of existing file are wrong? [WMWARN13]");
                        } else {
                            wm_warn("Failed to create image file $filename - permissions of output directory are wrong? [WMWARN14]");
                        }
                    }
                }
            }

            if (function_exists('imagecopyresampled')) {
                // if one is specified, and we can, write a thumbnail too
                if ($thumbnailfile != '') {
                    $result = false;
                    if ($this->width > $this->height) {
                        $factor=($thumbnailmax / $this->width);
                    } else {
                        $factor =($thumbnailmax / $this->height);
                    }

                    $this->thumb_width = $this->width * $factor;
                    $this->thumb_height = $this->height * $factor;

                    $imagethumb=imagecreatetruecolor($this->thumb_width, $this->thumb_height);
                    imagecopyresampled($imagethumb, $image, 0, 0, 0, 0, $this->thumb_width, $this->thumb_height, $this->width, $this->height);
                    $result = imagepng($imagethumb, $thumbnailfile);
                    imagedestroy($imagethumb);



                    if (($result===false)) {
                        if (file_exists($filename)) {
                            wm_warn("Failed to overwrite existing image file $filename - permissions of existing file are wrong? [WMWARN15]");
                        } else {
                            wm_warn("Failed to create image file $filename - permissions of output directory are wrong? [WMWARN16]");
                        }
                    }
                }
            } else {
                wm_warn("Skipping thumbnail creation, since we don't have the necessary function. [WMWARN17]");
            }
            imagedestroy($image);
        }
    }

    /**
     * So that memory is deallocated correctly, go through and remove all the
     * references to other objects. The PHP GC doesn't deal with circular
     * references, so this stops the memory usage from ballooning up.
     */
    function cleanUp()
    {
        $all_layers = array_keys($this->seen_zlayers);

        foreach ($all_layers as $z) {
            $this->seen_zlayers[$z] = null;
        }

        foreach ($this->links as $link) {
            $link->cleanUp();
            unset($link);
        }

        foreach ($this->nodes as $node) {
            // destroy all the images we created, to prevent memory leaks
            $node->cleanUp();
            unset($node);
        }

        // Clear up the other random hashes of information
        $this->dsinfocache = null;
        $this->colourtable = null;
        $this->usage_stats = null;
        $this->scales = null;
    }

    function processOverlib($mapItem, $dirs, $prefix)
    {
        // skip all this if it's a template node
        if ($mapItem->isTemplate()) {
            return;
        }

        $type = $mapItem->my_type();

        // find the middle of the map
        $centre_x = $this->width / 2;
        $centre_y = $this->height / 2;

        if ($type=='NODE') {
            $mid_x = $mapItem->x;
            $mid_y = $mapItem->y;
        }

        if ($type=='LINK') {
            $a_x = $this->nodes[$mapItem->a->name]->x;
            $a_y = $this->nodes[$mapItem->a->name]->y;

            $b_x = $this->nodes[$mapItem->b->name]->x;
            $b_y = $this->nodes[$mapItem->b->name]->y;

            $mid_x=($a_x + $b_x) / 2;
            $mid_y=($a_y + $b_y) / 2;
        }
        $left="";
        $above="";
        $img_extra = "";

        if ($mapItem->overlibwidth != 0) {
            $left="WIDTH," . $mapItem->overlibwidth . ",";
            $img_extra .= " WIDTH=$mapItem->overlibwidth";

            if ($mid_x > $centre_x) {
                $left.="LEFT,";
            }
        }

        if ($mapItem->overlibheight != 0) {
            $above="HEIGHT," . $mapItem->overlibheight . ",";
            $img_extra .= " HEIGHT=$mapItem->overlibheight";

            if ($mid_y > $centre_y) {
                $above.="ABOVE,";
            }
        }

        foreach ($dirs as $dir => $parts) {
            $caption = ($mapItem->overlibcaption[$dir] != '' ? $mapItem->overlibcaption[$dir] : $mapItem->name);
            $caption = $this->processString($caption, $mapItem);

            $overlibhtml = "onmouseover=\"return overlib('";

            $n = 0;
            if (sizeof($mapItem->overliburl[$dir]) > 0) {
                // print "ARRAY:".is_array($link->overliburl[$dir])."\n";
                foreach ($mapItem->overliburl[$dir] as $url) {
                    if ($n>0) {
                        $overlibhtml .= '&lt;br /&gt;';
                    }
                    $overlibhtml .= "&lt;img $img_extra src=" . $this->processString($url, $mapItem) . "&gt;";
                    $n++;
                }
            }

            if (trim($mapItem->notestext[$dir]) != '') {
                # put in a linebreak if there was an image AND notes
                if ($n>0) {
                    $overlibhtml .= '&lt;br /&gt;';
                }
                $note = $this->processString($mapItem->notestext[$dir], $mapItem);
                $note = htmlspecialchars($note, ENT_NOQUOTES);
                $note=str_replace("'", "\\&apos;", $note);
                $note=str_replace('"', "&quot;", $note);
                $overlibhtml .= $note;
            }
            $overlibhtml .= "',DELAY,250,${left}${above}CAPTION,'" . $caption . "');\"  onmouseout=\"return nd();\"";

            foreach ($parts as $part) {
                $areaname = $type.":" . $prefix . $mapItem->id. ":" . $part;

                $this->imap->setProp("extrahtml", $overlibhtml, $areaname);
            }
        }
    }

    function calculateImageMap()
    {
        wm_debug("Trace: PreloadMapHTML()\n");

        $allItems = $this->buildAllItemsList();

        // loop through everything. Figure out along the way if it's a node or a link

        foreach ($allItems as $mapItem) {
            $type = $mapItem->my_type();
            $prefix = substr($type, 0, 1);

            $dirs = array();
            if ($type == 'LINK') {
                $dirs = array(IN=>array(0, 2), OUT=>array(1, 3));
            }
            if ($type == 'NODE') {
                $dirs = array(IN=>array(0, 1, 2, 3));
            }

            // check to see if any of the relevant things have a value
            $change = "";
            foreach ($dirs as $d => $parts) {
                $change .= join('', $mapItem->overliburl[$d]);
                $change .= $mapItem->notestext[$d];
            }

            if ($this->htmlstyle == "overlib") {
                $this->processOverlib($mapItem, $dirs, $prefix);
            }

            // now look at infourls
            foreach ($dirs as $dir => $parts) {
                foreach ($parts as $part) {
                    $areaname = $type.":" . $prefix . $mapItem->id. ":" . $part;

                    if (($this->htmlstyle != 'editor') && ($mapItem->infourl[$dir] != '')) {
                        $this->imap->setProp("href", $this->processString($mapItem->infourl[$dir], $mapItem), $areaname);
                    }
                }
            }
        }

    }

    function asJS()
    {
        $javascript = '';

        $javascript .= "var Links = new Array();\n";
        $javascript .= "var LinkIDs = new Array();\n";

        foreach ($this->links as $link) {
            $javascript.=$link->asJS();
        }

        $javascript .= "var Nodes = new Array();\n";
        $javascript .= "var NodeIDs = new Array();\n";

        foreach ($this->nodes as $node) {
            $javascript.=$node->asJS();
        }


        $mapName = array_pop(explode("/", $this->configfile));

        $javascript .= "var Map = {\n";
        $javascript .= sprintf('  "file": %s,', jsEscape($mapName));
        $javascript .= sprintf('  "width": %s,', jsEscape($this->width));
        $javascript .= sprintf('  "height": %s,', jsEscape($this->height));
        $javascript .= sprintf('  "title": %s,', jsEscape($this->title));
        $javascript .= "\n};\n";

        return $javascript;
    }

    // This method MUST run *after* DrawMap. It relies on DrawMap to call the map-drawing bits
    // which will populate the ImageMap with regions.
    //
    // imagemapname is a parameter, so we can stack up several maps in the Cacti plugin with their own imagemaps
    function makeHTML($imagemapname = "weathermap_imap")
    {
        wm_debug("Trace: MakeHTML()\n");
        // calculateImageMap fills in the ImageMap info, ready for the HTML to be created.
        $this->calculateImageMap();

        $html='';

        $html .= '<div class="weathermapimage" style="margin-left: auto; margin-right: auto; width: '.$this->width.'px;" >';
        if ($this->imageuri != '') {
            $html.=sprintf(
                '<img id="wmapimage" src="%s" width="%d" height="%d" style="border: 0;" usemap="#%s"',
                $this->imageuri,
                $this->width,
                $this->height,
                $imagemapname
            );
            //$html .=  'alt="network weathermap" ';
            $html .= '/>';
        } else {
            $html.=sprintf(
                '<img id="wmapimage" src="%s" width="%d" height="%d" border="0" usemap="#%s"',
                $this->imagefile,
                $this->width,
                $this->height,
                $imagemapname
            );
            $html .= '/>';
        }
        $html .= '</div>';

        $html .= $this->generateSortedImagemap($imagemapname);

        return ($html);
    }

    function generateSortedImagemap($imagemapname)
    {
        $html = "\n" . '<map name="' . $imagemapname . '" id="' . $imagemapname . '">';

        $all_layers = array_keys($this->seen_zlayers);
        rsort($all_layers);

        wm_debug("Starting to dump imagemap in reverse Z-order...\n");
        // this is not precisely efficient, but it'll get us going
        // XXX - get Imagemap to store Z order, or map items to store the imagemap
        foreach ($all_layers as $z) {
            wm_debug("Writing HTML for layer $z\n");
            $z_items = $this->seen_zlayers[$z];
            if (is_array($z_items)) {
                wm_debug("   Found things for layer $z\n");

                // at z=1000, the legends and timestamps live
                if ($z == 1000) {
                    wm_debug("     Builtins fit here.\n");
                    // $html .= $this->imap->subHTML("LEGEND:",true,($this->context != 'editor'));
                    // $html .= $this->imap->subHTML("TIMESTAMP",true,($this->context != 'editor'));
                    foreach ($this->imap_areas as $areaname) {
                        // skip the linkless areas if we are in the editor - they're redundant
                        $html .= $this->imap->exactHTML($areaname, true, ($this->context != 'editor'));
                    }
                }

                // we reverse the array for each zlayer so that the imagemap order
                // will match up with the draw order (last drawn should be first hit)
                foreach (array_reverse($z_items) as $it) {
                    if ($it->name != 'DEFAULT' && $it->name != ":: DEFAULT ::") {
                        foreach ($it->imap_areas as $areaname) {
                            // skip the linkless areas if we are in the editor - they're redundant
                            $html .= $this->imap->exactHTML($areaname, true, ($this->context != 'editor'));
                        }
                    }
                }
            }
        }

        $html .= "</map>\n";

        return($html);
    }

    public function getValue($name)
    {
        wm_debug("Fetching %s\n", $name);
        if (property_exists($this, $name)) {
            return $this->$name;
        }
        throw new WMException("NoSuchProperty");
    }
}

// vim:ts=4:sw=4:
