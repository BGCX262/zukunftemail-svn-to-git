<?php
/**
 * @file
 * This module will make the date API available to other modules.
 * Designed to provide a light but flexible assortment of functions
 * and constants, with more functionality in additional files that
 * are not loaded unless other modules specifically include them.
 */

/**
 * Set up some constants.
 *
 * Includes standard date types, format strings, strict regex strings for ISO
 * and DATETIME formats (seconds are optional).
 *
 * The loose regex will find any variety of ISO date and time, with or
 * without time, with or without dashes and colons separating the elements,
 * and with either a 'T' or a space separating date and time.
 */
define('DATE_ISO',  'date');
define('DATE_UNIX', 'datestamp');
define('DATE_DATETIME', 'datetime');
define('DATE_ARRAY', 'array');
define('DATE_OBJECT', 'object');
define('DATE_ICAL', 'ical');

define('DATE_FORMAT_ISO', "Y-m-d\TH:i:s");
define('DATE_FORMAT_UNIX', "U");
define('DATE_FORMAT_DATETIME', "Y-m-d H:i:s");
define('DATE_FORMAT_ICAL', "Ymd\THis");
define('DATE_FORMAT_ICAL_DATE', "Ymd");
define('DATE_FORMAT_DATE', 'Y-m-d');

define('DATE_REGEX_ISO', '/(\d{4})?(-(\d{2}))?(-(\d{2}))?([T\s](\d{2}))?(:(\d{2}))?(:(\d{2}))?/');
define('DATE_REGEX_DATETIME', '/(\d{4})-(\d{2})-(\d{2})\s(\d{2}):(\d{2}):?(\d{2})?/');
define('DATE_REGEX_LOOSE', '/(\d{4})-?(\d{1,2})-?(\d{1,2})([T\s]?(\d{2}):?(\d{2}):?(\d{2})?(\.\d+)?(Z|[\+\-]\d{2}:?\d{2})?)?/');
define('DATE_REGEX_ICAL_DATE', '/(\d{4})(\d{2})(\d{2})/');
define('DATE_REGEX_ICAL_DATETIME', '/(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})(Z)?/');

/**
 * Core DateTime extension module used for as many date operations as possible in this new version.
 */

/**
 * Extend PHP DateTime class with granularity handling, merge functionality and
 * slightly more flexible initialization parameters.
 *
 * This class is a Drupal independent extension of the >= PHP 5.2 DateTime
 * class.
 *
 * @see FeedsDateTimeElement class
 */
class DateObject extends DateTime {
  public $granularity = array();
  public $errors = array();
  protected static $allgranularity = array('year', 'month', 'day', 'hour', 'minute', 'second', 'timezone');
  private $_serialized_time;
  private $_serialized_timezone;

  /**
   * Helper function to prepare the object during serialization.
   *
   * We are extending a core class and core classes cannot be serialized.
   *
   * Ref: http://bugs.php.net/41334, http://bugs.php.net/39821
   */
  public function __sleep(){
    $this->_serialized_time = $this->format('c');
    $this->_serialized_timezone = $this->getTimezone()->getName();
    return array('_serialized_time', '_serialized_timezone');
  }

  /**
   * Upon unserializing, we must re-build ourselves using local variables.
   */
  public function __wakeup() {
    $this->__construct($this->_serialized_time, new DateTimeZone($this->_serialized_timezone));
  }

  public function __toString() {
    return $this->format(DATE_FORMAT_DATETIME) . ' '. $this->getTimeZone()->getName();
  }

  /**
   * Overridden constructor.
   *
   * @param $time
   *   time string, flexible format including timestamp.
   * @param $tz
   *   PHP DateTimeZone object, string or NULL allowed, defaults to site timezone.
   * @param $format
   *   PHP date() type format for parsing. Doesn't support timezones; if you have a timezone, send NULL
   *   and the default constructor method will hopefully parse it.
   *   $format is recommended in order to use negative or large years, which php's parser fails on.
   */
  public function __construct($time = 'now', $tz = NULL, $format = NULL) {

    $this->timeOnly = FALSE;
    $this->dateOnly = FALSE;

    // Allow string timezones
    if (!empty($tz) && !is_object($tz)) {
      $tz = new DateTimeZone($tz);
    }

    // Default to the site timezone when not explicitly provided.
    elseif (empty($tz)) {
      $tz = date_default_timezone_object();
    }
    // Special handling for Unix timestamps expressed in the local timezone.
    // Create a date object in UTC and convert it to the local timezone.
    // Don't try to turn things like '2010' with a format of 'Y' into a timestamp.
    if (is_numeric($time) && (empty($format) || $format == 'U')) {
      // Assume timestamp.
      $time = "@". $time;
      $date = new DateObject($time, 'UTC');
      if ($tz->getName() != 'UTC') {
        $date->setTimezone($tz);
      }
      $time = $date->format(DATE_FORMAT_DATETIME);
      $format = DATE_FORMAT_DATETIME; 
    }

    if (is_array($time)) {
      // Assume we were passed an indexed array.
      if (empty($time['year']) && empty($time['month']) && empty($time['day'])) {
        $this->timeOnly = TRUE;
      }
      if (empty($time['hour']) && empty($time['minute']) && empty($time['second'])) {
        $this->dateOnly = TRUE;
      }
      $this->errors = $this->arrayErrors($time);
      // Make this into an ISO date, 
      // forcing a full ISO date even if some values are missing.
      $time = $this->toISO($time, TRUE);
      // We checked for errors already, skip the step of parsing the input values.
      $format = NULL;
    }

    // The parse function will also set errors on the date parts.
    if (!empty($format)) {
      $arg = self::$allgranularity;
      $element = array_pop($arg);
      while(!$this->parse($time, $tz, $format) && $element != 'year') {
        $element = array_pop($arg);
        $format = date_limit_format($format, $arg);
      }
      if ($element == 'year') {
        return FALSE;
      }
    } 
    elseif (is_string($time)) {
      // PHP < 5.3 doesn't like the GMT- notation for parsing timezones.
      $time = str_replace("GMT-", "-", $time);
      $time = str_replace("GMT+", "+", $time);
      // We are going to let the parent dateObject do a best effort attempt to turn this
      // string into a valid date. It might fail and we want to control the error messages.
      try {
        @parent::__construct($time, $tz);
      }
      catch (Exception $e) {
        $this->errors['date'] = $e;
        return;
      }
      $this->setGranularityFromTime($time, $tz);
    }
    // This tz was given as just an offset, which causes problems,
    // or the timezone was invalid.
    if (!$this->getTimezone() || !preg_match('/[a-zA-Z]/', $this->getTimezone()->getName())) {
      $this->setTimezone(new DateTimeZone("UTC"));
    }

  }

  /**
   * This function will keep this object's values by default.
   */
  public function merge(FeedsDateTime $other) {
    $other_tz = $other->getTimezone();
    $this_tz = $this->getTimezone();
    // Figure out which timezone to use for combination.
    $use_tz = ($this->hasGranularity('timezone') || !$other->hasGranularity('timezone')) ? $this_tz : $other_tz;

    $this2 = clone $this;
    $this2->setTimezone($use_tz);
    $other->setTimezone($use_tz);
    $val = $this2->toArray(TRUE);
    $otherval = $other->toArray();
    foreach (self::$allgranularity as $g) {
      if ($other->hasGranularity($g) && !$this2->hasGranularity($g)) {
        // The other class has a property we don't; steal it.
        $this2->addGranularity($g);
        $val[$g] = $otherval[$g];
      }
    }
    $other->setTimezone($other_tz);

    $this2->setDate($val['year'], $val['month'], $val['day']);
    $this2->setTime($val['hour'], $val['minute'], $val['second']);
    return $this2;
  }

  /**
   * Overrides default DateTime function. Only changes output values if
   * actually had time granularity. This should be used as a "converter" for
   * output, to switch tzs.
   *
   * In order to set a timezone for a datetime that doesn't have such
   * granularity, merge() it with one that does.
   */
  public function setTimezone($tz, $force = FALSE) {
    // PHP 5.2.6 has a fatal error when setting a date's timezone to itself.
    // http://bugs.php.net/bug.php?id=45038
    if (version_compare(PHP_VERSION, '5.2.7', '<') && $tz == $this->getTimezone()) {
      $tz = new DateTimeZone($tz->getName());
    }

    if (!$this->hasTime() || !$this->hasGranularity('timezone') || $force) {
      // this has no time or timezone granularity, so timezone doesn't mean much
      // We set the timezone using the method, which will change the day/hour, but then we switch back
      $arr = $this->toArray(TRUE);
      parent::setTimezone($tz);
      $this->setDate($arr['year'], $arr['month'], $arr['day']);
      $this->setTime($arr['hour'], $arr['minute'], $arr['second']);
      $this->addGranularity('timezone');
      return;
    }
    return parent::setTimezone($tz);
  }

  /**
   * Overrides base format function, formats this date according to its available granularity,
   * unless $force'ed not to limit to granularity.
   *
   * @TODO Incorporate translation into this so translated names will be provided.
   */
  public function format($format, $force = FALSE) {
    return parent::format($force ? $format : date_limit_format($format, $this->granularity));
  }

  /**
   * Safely adds a granularity entry to the array.
   */
  public function addGranularity($g) {
    $this->granularity[] = $g;
    $this->granularity = array_unique($this->granularity);
  }

  /**
   * Removes a granularity entry from the array.
   */
  public function removeGranularity($g) {
    if ($key = array_search($g, $this->granularity)) {
      unset($this->granularity[$key]);
    }
  }

  /**
   * Checks granularity array for a given entry.
   * Accepts an array, in which case all items must be present (AND's the query)
   */
  public function hasGranularity($g = NULL) {
    if ($g === NULL) {
      //just want to know if it has something valid
      //means no lower granularities without higher ones
      $last = TRUE;
      foreach(self::$allgranularity AS $arg) {
        if($arg == 'timezone') {
          continue;
        }
        if(in_array($arg, $this->granularity) && !$last) {
          return FALSE;
        }
        $last = in_array($arg, $this->granularity);
      }
      return in_array('year', $this->granularity);
    }
    if (is_array($g)) {
      foreach($g as $gran) {
        if (!in_array($gran, $this->granularity)) {
          return FALSE;
        }
      }
      return TRUE;
    }
    return in_array($g, $this->granularity);
  }

  // whether a date is valid for a given $granularity array, depending on if it's allowed to be flexible.
  public function validGranularity($granularity = NULL, $flexible = FALSE) {
    return $this->hasGranularity() && (!$granularity || $flexible || $this->hasGranularity($granularity));
  }

  /**
   * Returns whether this object has time set. Used primarily for timezone
   * conversion and formatting.
   */
  public function hasTime() {
    return $this->hasGranularity('hour');
  }

  /**
   * Returns whether the input values included a year.
   * Useful to use pseudo date objects when we only are interested in the time.
   */
  public function completeDate() {
    return $this->completeDate;
  }

  /**
   * In common usage we should not unset timezone through this.
   */
  public function limitGranularity($gran) {
    foreach($this->granularity AS $key => $val){
      if ($val != 'timezone' && !in_array($val, $gran)) {
        unset($this->granularity[$key]);
      }
    }
  }
  /**
   * Protected function to find the granularity given by the arguments to the
   * constructor.
   */
  protected function setGranularityFromTime($time, $tz) {
    $this->granularity = array();
    $temp = date_parse($time);
    // Special case for "now"
    if ($time == 'now') {
      $this->granularity = array('year', 'month', 'day', 'hour', 'minute', 'second');
    } 
    else {
      // This PHP date_parse() method currently doesn't have resolution down to seconds, so if
      // there is some time, all will be set.
      foreach (self::$allgranularity AS $g) {
        if ((isset($temp[$g]) && is_numeric($temp[$g])) || ($g == 'timezone' && (isset($temp['zone_type']) && $temp['zone_type'] > 0))) {
          $this->granularity[] = $g;
        }
      }
    }
    if ($tz) {
      $this->addGranularity('timezone');
    }
  }

  protected function parse($date, $tz, $format) {
    $array = date_format_patterns();
    foreach ($array as $key => $value) {
      $patterns[] = "`(^|[^\\\\\\\\])" . $key . "`"; // the letter with no preceding '\'
      $repl1[] = '${1}(.)';                  // a single character
      $repl2[] = '${1}(' . $value . ')';       // the
    }
    $patterns[] = "`\\\\\\\\([" . implode(array_keys($array)) . "])`";
    $repl1[] = '${1}';
    $repl2[] = '${1}';

    $format_regexp = preg_quote($format);

    // extract letters
    $regex1 = preg_replace($patterns, $repl1, $format_regexp, 1);
    $regex1 = str_replace('A', '(.)', $regex1);
    $regex1 = str_replace('a', '(.)', $regex1);
    preg_match('`^' . $regex1 . '$`', stripslashes($format), $letters);
    array_shift($letters);
    // extract values
    $regex2 = preg_replace($patterns, $repl2, $format_regexp, 1);
    $regex2 = str_replace('A', '(AM|PM)', $regex2);
    $regex2 = str_replace('a', '(am|pm)', $regex2);
    preg_match('`^' . $regex2 . '$`', $date, $values);
    array_shift($values);
    // if we did not find all the values for the patterns in the format, abort
    if (count($letters) != count($values)) {
      return FALSE;
    }
    $this->granularity = array();
    $final_date = array('hour' => 0, 'minute' => 0, 'second' => 0,
      'month' => 1, 'day' => 1, 'year' => 0);
    foreach ($letters as $i => $letter) {
      $value = $values[$i];
      switch ($letter) {
        case 'd':
        case 'j':
          $final_date['day'] = intval($value);
          $this->addGranularity('day');
          break;
        case 'n':
        case 'm':
          $final_date['month'] = intval($value);
          $this->addGranularity('month');
          break;
        case 'F':
          $array_month_long = array_flip(date_month_names());
          $final_date['month'] = $array_month_long[$value];
          $this->addGranularity('month');
          break;
        case 'M':
          $array_month = array_flip(date_month_names_abbr());
          $final_date['month'] = $array_month[$value];
          $this->addGranularity('month');
          break;
        case 'Y':
          $final_date['year'] = $value;
          $this->addGranularity('year');
          if (strlen($value) < 4) $this->errors['year'] = t('The year is invalid. Please check that entry includes four digits.');
          break;
        case 'y':
          $year = $value;
          // if no century, we add the current one ("06" => "2006")
          $final_date['year'] = str_pad($year, 4, substr(date("Y"), 0, 2), STR_PAD_LEFT);
          $this->addGranularity('year');
          break;
        case 'a':
        case 'A':
          $ampm = strtolower($value);
          break;
        case 'g':
        case 'h':
        case 'G':
        case 'H':
          $final_date['hour'] = intval($value);
          $this->addGranularity('hour');
          break;
        case 'i':
          $final_date['minute'] = intval($value);
          $this->addGranularity('minute');
          break;
        case 's':
          $final_date['second'] = intval($value);
          $this->addGranularity('second');
          break;
        case 'U':
          parent::__construct($value, $tz ? $tz : new DateTimeZone("UTC"));
          $this->addGranularity('year');
          $this->addGranularity('month');
          $this->addGranularity('day');
          $this->addGranularity('hour');
          $this->addGranularity('minute');
          $this->addGranularity('second');
          return $this;
          break;
      }
    }
    if (isset($ampm) && $ampm == 'pm' && $final_date['hour'] < 12) {
      $final_date['hour'] += 12;
    }
    elseif (isset($ampm) && $ampm == 'am' && $final_date['hour'] == 12) {
      $final_date['hour'] -= 12;
    }

    // Blank becomes current time, given TZ.
    parent::__construct('', $tz ? $tz : new DateTimeZone("UTC"));
    if ($tz) {
      $this->addGranularity('timezone');
    }
    
    // SetDate expects an integer value for the year, results can 
    // be unexpected if we feed it something like '0100' or '0000';
    $final_date['year'] = intval($final_date['year']);

    $this->errors += $this->arrayErrors($final_date);
    $granularity = drupal_map_assoc($this->granularity);

    // If the input value is '0000-00-00', PHP's date class will later incorrectly convert
    // it to something like '-0001-11-30' if we do setDate() here. If we don't do
    // setDate() here, it will default to the current date and we will lose any way to
    // tell that there was no date in the orignal input values. So set a flag we can use
    // later to tell that this date object was created using only time and that the date
    // values are artifical.
    if (empty($final_date['year']) && empty($final_date['month']) && empty($final_date['day'])) {
      $this->timeOnly = TRUE;
    }
    elseif (empty($this->errors)) {
      // setDate() expects a valid year, month, and day.
      // Set some defaults for dates that don't use this to
      // keep PHP from interpreting it as the last day of
      // the previous month or last month of the previous year.
      if (empty($granularity['month'])) {
        $final_date['month'] = 1;
      }
      if (empty($granularity['day'])) {
        $final_date['day'] = 1;
      }
      $this->setDate($final_date['year'], $final_date['month'], $final_date['day']);
    }

    if (!isset($final_date['hour']) && !isset($final_date['minute']) && !isset($final_date['second'])) {
      $this->dateOnly = TRUE;
    }
    elseif (empty($this->errors)) {
      $this->setTime($final_date['hour'], $final_date['minute'], $final_date['second']);
    }
    return $this;
  }

  /**
   * Helper to return all standard date parts in an array.
   * Will return '' for parts in which it lacks granularity.
   */
  public function toArray($force = FALSE) {
    return array(
      'year' => $this->format('Y', $force), 
      'month' => $this->format('n', $force), 
      'day' => $this->format('j', $force), 
      'hour' => intval($this->format('H', $force)), 
      'minute' => intval($this->format('i', $force)), 
      'second' => intval($this->format('s', $force)), 
      'timezone' => $this->format('e', $force),
    );
  }

  /**
    * Create an ISO date from an array of values.
    */
  public function toISO($arr, $full = FALSE) {
    // Add empty values to avoid errors
    // The empty values must create a valid date or we will get date slippage,
    // i.e. a value of 2011-00-00 will get interpreted as November of 2010 by PHP.
    if ($full) {
      $arr += array('year' => 0, 'month' => 1, 'day' => 1, 'hour' => 0, 'minute' => 0, 'second' => 0);
    }
    else {
      $arr += array('year' => '', 'month' => '', 'day' => '', 'hour' => '', 'minute' => '', 'second' => '');
    }
    $datetime = '';
    if ($arr['year'] !== '') {
      $datetime = date_pad(intval($arr['year']), 4);
      if ($full || $arr['month'] !== '') {
        $datetime .= '-'. date_pad(intval($arr['month']));
        if ($full || $arr['day'] !== '') { 
          $datetime .= '-'. date_pad(intval($arr['day']));
        }
      }
    }
    if ($arr['hour'] !== '') {
      $datetime .= $datetime ? 'T' : '';
      $datetime .= date_pad(intval($arr['hour']));
      if ($full || $arr['minute'] !== '') {
        $datetime.= ':'. date_pad(intval($arr['minute']));
        if ($full || $arr['second'] !== '') {
          $datetime .= ':'. date_pad(intval($arr['second']));
        }
      }
    }
    return $datetime;
  }

  /**
   * Force an incomplete date to be valid, for instance to add
   * a valid year, month, and day if only the time has been defined.
   *
   * @param $date
   *   An array of date parts or a datetime string with values to be forced into date.
   * @param $format
   *   The format of the date.
   * @param $default 
   *   'current' - default to current day values.
   *   'first' - default to the first possible valid value.
   */   
  public function setFuzzyDate($date, $format = NULL, $default = 'first') {
    $timezone = $this->getTimeZone() ? $this->getTimeZone()->getName() : NULL;
    $comp = new DateObject($date, $timezone, $format);
    $arr = $comp->toArray(TRUE);
    foreach ($arr as $key => $value) {
      // Set to intval here and then test that it is still an integer.
      // Needed because sometimes valid integers come through as strings.
      $arr[$key] = $this->forceValid($key, intval($value), $default, $arr['month'], $arr['year']);
    }
    $this->setDate($arr['year'], $arr['month'], $arr['day']);
    $this->setTime($arr['hour'], $arr['minute'], $arr['second']);
  }

  /**
   * Convert a date part into something that will produce a valid date.
   */
  protected function forceValid($part, $value, $default = 'first', $month = NULL, $year = NULL) {
    $now = date_now();
    switch ($part) {
      case 'year':
        $fallback = $now->format('Y');
        return !is_int($value) || empty($value) || $value < variable_get('date_min_year', 1) || $value > variable_get('date_max_year', 4000) ? $fallback : $value;
        break;
      case 'month':
        $fallback = $default == 'first' ? 1 : $now->format('n');
        return !is_int($value) || empty($value) || $value <= 0 || $value > 12 ? $fallback : $value;
        break;
      case 'day':
        $fallback = $default == 'first' ? 1 : $now->format('j');
        $max_day = isset($year) && isset($month) ? date_days_in_month($year, $month) : 31;
        return !is_int($value) || empty($value) || $value <= 0 || $value > $max_day ? $fallback : $value;
        break;
      case 'hour':
        $fallback = $default == 'first' ? 0 : $now->format('G');
        return !is_int($value) || $value < 0 || $value > 23 ? $fallback : $value;  
        break;
      case 'minute':
        $fallback = $default == 'first' ? 0 : $now->format('i');
        return !is_int($value) || $value < 0 || $value > 59 ? $fallback : $value; 
        break; 
      case 'second':
        $fallback = $default == 'first' ? 0 : $now->format('s');
        return !is_int($value) || $value < 0 || $value > 59 ? $fallback : $value;  
        break;
    }
  }

  // Find possible errors in an array of date part values.
  // The forceValid() function will change an invalid value to a valid one,
  // so we just need to see if the value got altered.
  public function arrayErrors($arr) {
    $errors = array();
    $now = date_now();
	$default_month = !empty($arr['month']) ? $arr['month'] : $now->format('n');
    $default_year = !empty($arr['year']) ? $arr['year'] : $now->format('Y');

    foreach ($arr as $part => $value) {
      // Avoid false errors when a numeric value is input as a string by forcing it numeric.
      $value = intval($value);
	  if (!empty($value) && $this->forceValid($part, $value, 'now', $default_month, $default_year) != $value) {
        // Use a switchcase to make translation easier by providing a different message for each part.
        switch($part) {
          case 'year':
            $errors['year'] = t('The year is invalid.');
            break;
          case 'month':
            $errors['month'] = t('The month is invalid.');
            break;
          case 'day':
            $errors['day'] = t('The day is invalid.');
            break;
          case 'hour':
            $errors['hour'] = t('The hour is invalid.');
            break;
          case 'minute':
            $errors['minute'] = t('The minute is invalid.');
            break;
          case 'second':
            $errors['second'] = t('The second is invalid.');
            break;
        }
      }
    }
    return $errors;
  }

  /**
   * Compute difference between two days using a given measure.
   *
   * @param mixed $date1
   *   the starting date
   * @param mixed $date2
   *   the ending date
   * @param string $measure
   *   'years', 'months', 'weeks', 'days', 'hours', 'minutes', 'seconds'
   * @param string $type
   *   the type of dates provided:
   *   DATE_OBJECT, DATE_DATETIME, DATE_ISO, DATE_UNIX, DATE_ARRAY
   */
  public function difference($date2_in, $measure = 'seconds') {
    // Create cloned objects or original dates will be impacted by
    // the date_modify() operations done in this code.
    $date1 = clone($this);
    $date2 = clone($date2_in);
    if (is_object($date1) && is_object($date2)) {
      $diff = date_format($date2, 'U') - date_format($date1, 'U');
      if ($diff == 0 ) {
        return 0;
      }
      elseif ($diff < 0) {
        // Make sure $date1 is the smaller date.
        $temp = $date2;
        $date2 = $date1;
        $date1 = $temp;
        $diff = date_format($date2, 'U') - date_format($date1, 'U');
      }
      $year_diff = intval(date_format($date2, 'Y') - date_format($date1, 'Y'));
      switch ($measure) {

        // The easy cases first.
        case 'seconds':
          return $diff;
        case 'minutes':
          return $diff / 60;
        case 'hours':
          return $diff / 3600;
        case 'years':
          return $year_diff;

        case 'months':
          $format = 'n';
          $item1 = date_format($date1, $format);
          $item2 = date_format($date2, $format);
          if ($year_diff == 0) {
            return intval($item2 - $item1);
          }
          else {
            $item_diff = 12 - $item1;
            $item_diff += intval(($year_diff - 1) * 12);
            return $item_diff + $item2;
         }
         break;

        case 'days':
          $format = 'z';
          $item1 = date_format($date1, $format);
          $item2 = date_format($date2, $format);
          if ($year_diff == 0) {
            return intval($item2 - $item1);
          }
          else {
            $item_diff = date_days_in_year($date1) - $item1;
            for ($i = 1; $i < $year_diff; $i++) {
              date_modify($date1, '+1 year');
              $item_diff += date_days_in_year($date1);
            }
            return $item_diff + $item2;
         }
         break;

        case 'weeks':
          $week_diff = date_format($date2, 'W') - date_format($date1, 'W');
          $year_diff = date_format($date2, 'o') - date_format($date1, 'o');
          for ($i = 1; $i <= $year_diff; $i++) {
            date_modify($date1, '+1 year');
            $week_diff += date_iso_weeks_in_year($date1);
          }
          return $week_diff;
      }
    }
    return NULL;
  }
}

function date_db_type() {
  return $GLOBALS['databases']['default']['default']['driver'];
}

/**
 * Helper function for getting the format string for a date type.
 */
function date_type_format($type) {
  switch ($type) {
    case DATE_ISO:
      return DATE_FORMAT_ISO;
    case DATE_UNIX:
      return DATE_FORMAT_UNIX;
    case DATE_DATETIME:
      return DATE_FORMAT_DATETIME;
    case DATE_ICAL:
      return DATE_FORMAT_ICAL;
  }
}

/**
 * Implement hook_init().
 */
function date_api_init() {
  drupal_add_css(drupal_get_path('module', 'date_api') . '/date.css', array('weight' => CSS_THEME));
}

/**
 * An untranslated array of month names
 *
 * Needed for css, translation functions, strtotime(), and other places
 * that use the English versions of these words.
 *
 * @return
 *   an array of month names
 */
function date_month_names_untranslated() {
  static $month_names;
  if (empty($month_names)) {
    $month_names = array(1 => 'January', 2 => 'February', 3 => 'March',
      4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July',
      8 => 'August', 9 => 'September', 10 => 'October',
      11 => 'November', 12 => 'December');
  }
  return $month_names;
}

/**
 * Returns a translated array of month names.
 *
 * @param $required
 *   If not required, will include a blank value at the beginning of the list.
 *
 * @return
 *   An array of month names
 */
function date_month_names($required = FALSE) {
  $month_names = array();
  foreach (date_month_names_untranslated() as $key => $month) {
    $month_names[$key] = t($month, array(), array('context' => 'Long month name'));
  }
  $none = array('' => '');
  return !$required ? $none + $month_names : $month_names;
}

/**
 * A translated array of month name abbreviations
 *
 * @param $required
 *   If not required, will include a blank value at the beginning of the list.
 * @return
 *   an array of month abbreviations
 */
function date_month_names_abbr($required = FALSE, $length = 3) {
  $month_names = array();
  foreach (date_month_names_untranslated() as $key => $month) {
    $month_names[$key] = t(substr($month, 0, $length), array(), array('context' => 'month_abbr'));
  }
  $none = array('' => '');
  return !$required ? $none + $month_names : $month_names;
}

/**
 * An untranslated array of week days
 *
 * Needed for css, translation functions, strtotime(), and other places
 * that use the English versions of these words.
 *
 * @return
 *   an array of week day names
 */
function date_week_days_untranslated($refresh = TRUE) {
  static $weekdays;
  if ($refresh || empty($weekdays)) {
    $weekdays = array(0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday',
      3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday',
      6 => 'Saturday');
  }
  return $weekdays;
}

/**
 * Returns a translated array of week names.
 *
 * @param $required
 *   If not required, will include a blank value at the beginning of the array.
 *
 * @return
 *   An array of week day names
 */
function date_week_days($required = FALSE, $refresh = TRUE) {
  $weekdays = array();
  foreach (date_week_days_untranslated() as $key => $day) {
    $weekdays[$key] = t($day, array(), array('context' => ''));
  }
  $none = array('' => '');
  return !$required ? $none + $weekdays : $weekdays;
}

/**
 * An translated array of week day abbreviations.
 *
 * @param $required
 *   If not required, will include a blank value at the beginning of the array.
 * @return
 *   an array of week day abbreviations
 */
function date_week_days_abbr($required = FALSE, $refresh = TRUE, $length = 3) {
  $weekdays = array();
  switch ($length) {
    case 1:
      $context = 'day_abbr1';
      break;
    case 2:
      $context = 'day_abbr2';
      break;
    default:
      $context = '';
      break; 
  }
  foreach (date_week_days_untranslated() as $key => $day) {
    $weekdays[$key] = t(substr($day, 0, $length), array(), array('context' => $context));
  }
  $none = array('' => '');
  return !$required ? $none + $weekdays : $weekdays;
}

/**
 * Order weekdays
 *   Correct weekdays array so first day in array matches the first day of
 *   the week. Use to create things like calendar headers.
 *
 * @param array $weekdays
 * @return array
 */
function date_week_days_ordered($weekdays) {
  if (variable_get('date_first_day', 1) > 0) {
    for ($i = 1; $i <= variable_get('date_first_day', 1); $i++) {
      $last = array_shift($weekdays);
      array_push($weekdays, $last);
    }
  }
  return $weekdays;
}

/**
 * An array of years.
 *
 * @param int $min
 *   the minimum year in the array
 * @param int $max
 *   the maximum year in the array
 * @param $required
 *   If not required, will include a blank value at the beginning of the array.
 * @return
 *   an array of years in the selected range
 */
function date_years($min = 0, $max = 0, $required = FALSE) {
  // Have to be sure $min and $max are valid values;
  if (empty($min)) $min = intval(date('Y', REQUEST_TIME) - 3);
  if (empty($max)) $max = intval(date('Y', REQUEST_TIME) + 3);
  $none = array(0 => '');
  return !$required ? $none + drupal_map_assoc(range($min, $max)) : drupal_map_assoc(range($min, $max));
}

/**
 * An array of days.
 *
 * @param $required
 *   If not required, returned array will include a blank value.
 * @param integer $month (optional)
 * @param integer $year (optional)
 * @return
 *   an array of days for the selected month.
 */
function date_days($required = FALSE, $month = NULL, $year = NULL) {
  // If we have a month and year, find the right last day of the month.
  if (!empty($month) && !empty($year)) {
    $date = new DateObject($year . '-' . $month . '-01 00:00:00', 'UTC');
    $max = $date->format('t');
  }
  // If there is no month and year given, default to 31.
  if (empty($max)) $max = 31;
  $none = array(0 => '');
  return !$required ? $none + drupal_map_assoc(range(1, $max)) : drupal_map_assoc(range(1, $max));
}

/**
 * An array of hours.
 *
 * @param string $format
 * @param $required
 *   If not required, returned array will include a blank value.
 * @return
 *   an array of hours in the selected format.
 */
function date_hours($format = 'H', $required = FALSE) {
  $hours = array();
  if ($format == 'h' || $format == 'g') {
    $min = 1;
    $max = 12;
  }
  else  {
    $min = 0;
    $max = 23;
  }
  for ($i = $min; $i <= $max; $i++) {
    $hours[$i] = $i < 10 && ($format == 'H' || $format == 'h') ? "0$i" : $i;
  }
  $none = array('' => '');
  return !$required ? $none + $hours : $hours;
}

/**
 * An array of minutes.
 *
 * @param string $format
 * @param $required
 *   If not required, returned array will include a blank value.
 * @return
 *   an array of minutes in the selected format.
 */
function date_minutes($format = 'i', $required = FALSE, $increment = 1) {
  $minutes = array();
  // Have to be sure $increment has a value so we don't loop endlessly;
  if (empty($increment)) $increment = 1;
  for ($i = 0; $i < 60; $i += $increment) {
    $minutes[$i] = $i < 10 && $format == 'i' ? "0$i" : $i;
  }
  $none = array('' => '');
  return !$required ? $none + $minutes : $minutes;
}

/**
 * An array of seconds.
 *
 * @param string $format
 * @param $required
 *   If not required, returned array will include a blank value.
 * @return array an array of seconds in the selected format.
 */
function date_seconds($format = 's', $required = FALSE, $increment = 1) {
  $seconds = array();
  // Have to be sure $increment has a value so we don't loop endlessly;
  if (empty($increment)) $increment = 1;
  for ($i = 0; $i < 60; $i += $increment) {
    $seconds[$i] = $i < 10 && $format == 's' ? "0$i" : $i;
  }
  $none = array('' => '');
  return !$required ? $none + $seconds : $seconds;
}

/**
 * An array of am and pm options.
 * @param $required
 *   If not required, returned array will include a blank value.
 * @return array an array of am pm options.
 */
function date_ampm($required = FALSE) {
  $none = array('' => '');
  $ampm = array('am' => t('am', array(), array('context' => 'ampm')), 'pm' => t('pm', array(), array('context' => 'ampm')));
  return !$required ? $none + $ampm : $ampm;
}

/**
 * Array of regex replacement strings for date format elements.
 * Used to allow input in custom formats. Based on work done for
 * the Date module by Yves Chedemois (yched).
 *
 * @return array of date() format letters and their regex equivalents.
 */
function date_format_patterns($strict = FALSE) {
  return array(
    'd' => '\d{' . ($strict ? '2' : '1,2') . '}', 
    'm' => '\d{' . ($strict ? '2' : '1,2') . '}', 
    'h' => '\d{' . ($strict ? '2' : '1,2') . '}',      
    'H' => '\d{' . ($strict ? '2' : '1,2') . '}',   
    'i' => '\d{' . ($strict ? '2' : '1,2') . '}',
    's' => '\d{' . ($strict ? '2' : '1,2') . '}',
    'j' => '\d{1,2}',    'N' => '\d',      'S' => '\w{2}',
    'w' => '\d',       'z' => '\d{1,3}',    'W' => '\d{1,2}',
    'n' => '\d{1,2}',  't' => '\d{2}',      'L' => '\d',      'o' => '\d{4}',
    'Y' => '-?\d{1,6}',    'y' => '\d{2}',      'B' => '\d{3}',   'g' => '\d{1,2}',
    'G' => '\d{1,2}',  'e' => '\w*',        'I' => '\d',      'T' => '\w*',
    'U' => '\d*',      'z' => '[+-]?\d*',   'O' => '[+-]?\d{4}',
    //Using S instead of w and 3 as well as 4 to pick up non-ASCII chars like German umlaute
    'D' => '\S{3,4}',    'l' => '\S*', 'M' => '\S{3,4}', 'F' => '\S*',
    'P' => '[+-]?\d{2}\:\d{2}',
    'O' => '[+-]\d{4}',
    'c' => '(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})([+-]?\d{2}\:\d{2})',
    'r' => '(\w{3}), (\d{2})\s(\w{3})\s(\d{2,4})\s(\d{2}):(\d{2}):(\d{2})([+-]?\d{4})?',
    );
}

/**
 * Array of granularity options and their labels
 *
 * @return array
 */
function date_granularity_names() {
  return array(
    'year' => t('Year', array(), array('context' => 'datetime')), 
    'month' => t('Month', array(), array('context' => 'datetime')), 
    'day' => t('Day', array(), array('context' => 'datetime')),
    'hour' => t('Hour', array(), array('context' => 'datetime')), 
    'minute' => t('Minute', array(), array('context' => 'datetime')), 
    'second' => t('Second', array(), array('context' => 'datetime')),
    );
}

/**
 * Sort a granularity array.
 */
function date_granularity_sorted($granularity) {
  return array_intersect(array('year', 'month', 'day', 'hour', 'minute', 'second'), $granularity);
}

/**
 * Give a granularity $precision, return an array of 
 * all the possible granularity elements.
 */
function date_granularity_array_from_precision($precision) {
  $granularity_array = array('year', 'month', 'day', 'hour', 'minute', 'second');
  switch(($precision)) {
    case 'year':
      return array_slice($granularity_array, -6);
    case 'month':
      return array_slice($granularity_array, -5);
    case 'day':
      return array_slice($granularity_array, -4);
    case 'hour':
      return array_slice($granularity_array, -3);
    case 'minute':
      return array_slice($granularity_array, -2);
    default:
      return $granularity_array;
  }
}

/**
 * Give a granularity array, return the highest precision.
 */
function date_granularity_precision($granularity_array) {
  $input = clone($granularity_array);
  return array_pop($input);
}

/**
 * Construct an appropriate DATETIME format string for the granularity of an item.
 */
function date_granularity_format($granularity) {
  if (is_array($granularity)) {
    $granularity = date_granularity_precision($granularity);
  }
  $format = 'Y-m-d H:i:s';
  switch ($granularity) {
    case 'year':
      return substr($format, 0, 1);
    case 'month':
      return substr($format, 0, 3);
    case 'day':
      return substr($format, 0, 5);
    case 'hour';
      return substr($format, 0, 7);
    case 'minute':
      return substr($format, 0, 9);
    default:
      return $format;
  } 
}

/**
 * A translated array of timezone names.
 * Cache the untranslated array, make the translated array a static variable.
 *
 * @param $required
 *   If not required, returned array will include a blank value.
 * @return
 *   an array of timezone names
 */
function date_timezone_names($required = FALSE, $refresh = FALSE) {
  static $zonenames;
  if (empty($zonenames) || $refresh) {
    $cached = cache_get('date_timezone_identifiers_list');
    $zonenames = !empty($cached) ? $cached->data : array();
    if ($refresh || empty($cached) || empty($zonenames)) {
      $data = timezone_identifiers_list();
      asort($data);
      foreach ($data as $delta => $zone) {
        // Because many time zones exist in PHP only for backward 
        // compatibility reasons and should not be used, the list is 
        // filtered by a regular expression.
        if (preg_match('!^((Africa|America|Antarctica|Arctic|Asia|Atlantic|Australia|Europe|Indian|Pacific)/|UTC$)!', $zone)) {
          $zonenames[$zone] = $zone;
        }      
      }

      if (!empty($zonenames)) {
        cache_set('date_timezone_identifiers_list', $zonenames);
      }
    }
    foreach ($zonenames as $zone) {
      $zonenames[$zone] = t('!timezone', array('!timezone' => $zone));
    }
  }
  $none = array('' => '');
  return !$required ? $none + $zonenames : $zonenames;
}

/**
 * An array of timezone abbreviations that the system allows.
 * Cache an array of just the abbreviation names because the
 * whole timezone_abbreviations_list is huge so we don't want
 * to get it more than necessary.
 *
 * @return array
 */
function date_timezone_abbr($refresh = FALSE) {
  $cached = cache_get('date_timezone_abbreviations');
  $data = isset($cached->data) ? $cached->data : array();
  if (empty($data) || $refresh) {
    $data = array_keys(timezone_abbreviations_list());
    cache_set('date_timezone_abbreviations', $data);
  }
  return $data;
}

/**
 * Reworked from Drupal's format_date function to handle pre-1970 and
 * post-2038 dates and accept a date object instead of a timestamp as input.
 *
 * Translates formatted date results, unlike PHP function date_format().
 * Should only be used for display, not input, because it can't be parsed.
 *
 * @param $oject
 *   A date object.
 * @param $type
 *   The format to use. Can be "small", "medium" or "large" for the preconfigured
 *   date formats. If "custom" is specified, then $format is required as well.
 * @param $format
 *   A PHP date format string as required by date(). A backslash should be used
 *   before a character to avoid interpreting the character as part of a date
 *   format.
 * @return
 *   A translated date string in the requested format.
 */
function date_format_date($date, $type = 'medium', $format = '', $langcode = NULL) {
  if (empty($date)) {
    return '';
  }
  switch ($type) {
    case 'small':
    case 'short':
      $format = variable_get('date_format_short', 'm/d/Y - H:i');
      break;
    case 'large':
    case 'long':
      $format = variable_get('date_format_long', 'l, F j, Y - H:i');
      break;
    case 'custom':
      $format = $format;
      break;
    case 'medium':
    default:
      $format = variable_get('date_format_medium', 'D, m/d/Y - H:i');
  }
  $format = date_limit_format($format, $date->granularity);
  $max = strlen($format);
  $datestring = '';
  for ($i = 0; $i < $max; $i++) {
    $c = $format[$i];
    switch ($c) {
      case 'l':
        $datestring .= t($date->format('l'), array(), array('context' => '', 'langcode' => $langcode));
        break;
      case 'D':
        $datestring .= t($date->format('D'), array(), array('context' => '', 'langcode' => $langcode));
        break;
      case 'F':
        $datestring .= t($date->format('F'), array(), array('context' => 'Long month name', 'langcode' => $langcode));
        break;  
      case 'M':
        $datestring .= t($date->format('M'), array(), array('context' => 'month_abbr', 'langcode' => $langcode));
        break;  
      case 'A':
      case 'a':
        $datestring .= t($date->format($c), array(), array('context' => 'ampm', 'langcode' => $langcode));
        break;  
      // The timezone name translations can use t().  
      case 'e':
      case 'T':
        $datestring .= t($date->format($c));
        break;
      // Remaining date parts need no translation.
      case 'O':
        $datestring .= sprintf('%s%02d%02d', (date_offset_get($date) < 0 ? '-' : '+'), abs(date_offset_get($date) / 3600), abs(date_offset_get($date) % 3600) / 60);
        break;
      case 'P':
        $datestring .= sprintf('%s%02d:%02d', (date_offset_get($date) < 0 ? '-' : '+'), abs(date_offset_get($date) / 3600), abs(date_offset_get($date) % 3600) / 60);
        break;
      case 'Z':
        $datestring .= date_offset_get($date);
        break;              
      case '\\':
        $datestring .= $format[++$i];
        break;
      case 'r':
        $datestring .= date_format_date($date, 'custom', 'D, d M Y H:i:s O', $langcode);
        break;        
      default:
        if (strpos('BdcgGhHiIjLmnNosStTuUwWYyz', $c) !== FALSE) {
          $datestring .= $date->format($c);
        }
        else {
          $datestring .= $c;
        }
    }
  }
  return $datestring;
}

/**
 * An override for interval formatting that adds past and future context
 *
 * @param DateTime $date
 * @param integer $granularity
 * @return formatted string
 */
function date_format_interval($date, $granularity = 2) {
  // If no date is sent, then return nothing
  if (empty($date)) {
    return NULL;
  }

  $interval = REQUEST_TIME - $date->format('U');
  if ($interval > 0 ) {
    return t('!time ago', array('!time' => format_interval($interval, $granularity)));
  } 
  else {
    return format_interval(abs($interval), $granularity);
  }
}

/**
 * A date object for the current time.
 *
 * @param $timezone
 *   Optional method to force time to a specific timezone,
 *   defaults to user timezone, if set, otherwise site timezone.
 * @return object date
 */
function date_now($timezone = NULL) {
  return new DateObject('now', $timezone);
}

function date_timezone_is_valid($timezone) {
  static $timezone_names;
  if (empty($timezone_names)) {
    $timezone_names = array_keys(date_timezone_names(TRUE));
  }
  if (!in_array($timezone, $timezone_names)) {
    return FALSE;
  }
  return TRUE;
}

/**
 * Return a timezone name to use as a default.
 *
 * @return a timezone name
 *   Identify the default timezone for a user, if available, otherwise the site.
 *   Must return a value even if no timezone info has been set up.
 */
function date_default_timezone($check_user = TRUE) {
  global $user;
  if ($check_user && variable_get('configurable_timezones', 1) && !empty($user->timezone)) {
    return $user->timezone;
  }
  else {
    $default = variable_get('date_default_timezone', '');
    return empty($default) ? 'UTC' : $default;
  }
}

/**
 * A timezone object for the default timezone.
 *
 * @return a timezone name
 *   Identify the default timezone for a user, if available, otherwise the site.
 */
function date_default_timezone_object($check_user = TRUE) {
  $timezone = date_default_timezone($check_user);
  return timezone_open(date_default_timezone($check_user));
}

/**
 * Identify the number of days in a month for a date.
 */
function date_days_in_month($year, $month) {
  // Pick a day in the middle of the month to avoid timezone shifts.
  $datetime = date_pad($year, 4) . '-' . date_pad($month) . '-15 00:00:00';
  $date = new DateObject($datetime);
  return $date->format('t');
}

/**
 * Identify the number of days in a year for a date.
 *
 * @param mixed $date
 * @return integer
 */
function date_days_in_year($date = NULL) {
  if (empty($date)) {
    $date = date_now();
  }
  elseif (!is_object($date)) {
    $date = new DateObject($date);
  }
  if (is_object($date)) {
    if ($date->format('L')) {
      return 366;
    }
    else {
      return 365;
    }
  }
  return NULL;
}

/**
 * Identify the number of ISO weeks in a year for a date.
 *
 * December 28 is always in the last ISO week of the year.
 *
 * @param mixed $date
 * @return integer
 */
function date_iso_weeks_in_year($date = NULL) {
  if (empty($date)) {
    $date = date_now();
  }
  elseif (!is_object($date)) {
    $date = new DateObject($date);
  }
  if (is_object($date)) {
    date_date_set($date, $date->format('Y'), 12, 28);
    return $date->format('W');
  }
  return NULL;
}

/**
 * Returns day of week for a given date (0 = Sunday).
 *
 * @param mixed  $date
 *   a date, default is current local day
 * @return
 *    the number of the day in the week
 */
function date_day_of_week($date = NULL) {
  if (empty($date)) {
    $date = date_now();
  }
  elseif (!is_object($date)) {
    $date = new DateObject($date);
  }
  if (is_object($date)) {
    return $date->format('w');
  }
  return NULL;
}

/**
 * Returns translated name of the day of week for a given date.
 *
 * @param mixed  $date
 *   a date, default is current local day
 * @param string $abbr
 *   Whether to return the abbreviated name for that day
 * @return
 *    the name of the day in the week for that date
 */
function date_day_of_week_name($date = NULL, $abbr = TRUE) {
  if (!is_object($date)) {
    $date = new DateObject($date);
  }
  $dow = date_day_of_week($date);
  $days = $abbr ? date_week_days_abbr() : date_week_days();
  return $days[$dow];
}

/**
 * Start and end dates for a calendar week, adjusted to use the
 * chosen first day of week for this site.
 */
function date_week_range($week, $year) {
  if (variable_get('date_api_use_iso8601', FALSE)) {
    return date_iso_week_range($week, $year);
  }
  $min_date = new DateObject($year . '-01-01 00:00:00');
  $min_date->setTimezone(date_default_timezone_object());

  // move to the right week
  date_modify($min_date, '+' . strval(7 * ($week - 1)) . ' days');

  // move backwards to the first day of the week
  $first_day = variable_get('date_first_day', 1);
  $day_wday = date_format($min_date, 'w');
  date_modify($min_date, '-' . strval((7 + $day_wday - $first_day) % 7) . ' days');

  // move forwards to the last day of the week
  $max_date = clone($min_date);
  date_modify($max_date, '+7 days');

  if (date_format($min_date, 'Y') != $year) {
    $min_date = new DateObject($year . '-01-01 00:00:00');
  }
  return array($min_date, $max_date);
}

/** 
 * Start and end dates for an ISO week.
 */
function date_iso_week_range($week, $year) {

  // Get to the last ISO week of the previous year.
  $min_date = new DateObject(($year - 1) .'-12-28 00:00:00');
  date_timezone_set($min_date, date_default_timezone_object());

  // Find the first day of the first ISO week in the year.
  date_modify($min_date, '+1 Monday');

  // Jump ahead to the desired week for the beginning of the week range.
  if ($week > 1) {
    date_modify($min_date, '+ '. ($week - 1) .' weeks');
  }

  // move forwards to the last day of the week
  $max_date = clone($min_date);
  date_modify($max_date, '+7 days');
  return array($min_date, $max_date);
}

/**
 * The number of calendar weeks in a year.
 * 
 * PHP week functions return the ISO week, not the calendar week.
 *
 * @param int $year
 * @return int number of calendar weeks in selected year.
 */
function date_weeks_in_year($year) {
  $date = new DateObject(($year + 1) . '-01-01 12:00:00', 'UTC');
  date_modify($date, '-1 day');
  return date_week($date->format('Y-m-d'));
}

/**
 * The calendar week number for a date.
 * 
 * PHP week functions return the ISO week, not the calendar week.
 *
 * @param string $date, in the format Y-m-d
 * @return int calendar week number.
 */
function date_week($date) {
  $date = substr($date, 0, 10);
  $parts = explode('-', $date);

  $date = new DateObject($date . ' 12:00:00', 'UTC');

  // If we are using ISO weeks, this is easy.
  if (variable_get('date_api_use_iso8601', FALSE)) {
    return intval($date->format('W'));
  }

  $year_date = new DateObject($parts[0] . '-01-01 12:00:00', 'UTC');
  $week = intval($date->format('W'));
  $year_week = intval(date_format($year_date, 'W'));
  $date_year = intval($date->format('o'));

  // remove the leap week if it's present
  if ($date_year > intval($parts[0])) {
    $last_date = clone($date);
    date_modify($last_date, '-7 days');
    $week = date_format($last_date, 'W') + 1;
  } 
  elseif ($date_year < intval($parts[0])) {
    $week = 0;
  }
  if ($year_week != 1) $week++;

  // convert to ISO-8601 day number, to match weeks calculated above
  $iso_first_day = 1 + (variable_get('date_first_day', 1) + 6) % 7;

  // if it's before the starting day, it's the previous week
  if (intval($date->format('N')) < $iso_first_day) $week--;

  // if the year starts before, it's an extra week at the beginning
  if (intval(date_format($year_date, 'N')) < $iso_first_day) $week++;

  return $week;
}

/**
 * Helper function to left pad date parts with zeros.
 * Provided because this is needed so often with dates.
 *
 * @param int $value
 *   the value to pad
 * @param int $size
 *   total size expected, usually 2 or 4
 * @return string the padded value
 */
function date_pad($value, $size = 2) {
  return sprintf("%0" . $size . "d", $value);
}

 /**
 *  Function to figure out if any time data is to be collected or displayed.
 *
 *  @param granularity
 *    an array like ('year', 'month', 'day', 'hour', 'minute', 'second');
 */
function date_has_time($granularity) {
  if (!is_array($granularity)) $granularity = array();
  return sizeof(array_intersect($granularity, array('hour', 'minute', 'second'))) > 0 ? TRUE : FALSE;
}

function date_has_date($granularity) {
  if (!is_array($granularity)) $granularity = array();
  return sizeof(array_intersect($granularity, array('year', 'month', 'day'))) > 0 ? TRUE : FALSE;
}

/**
 * Rewrite a format string so it only includes elements from a
 * specified granularity array.
 *
 * Example:
 *   date_limit_format('F j, Y - H:i', array('year', 'month', 'day'));
 *   returns 'F j, Y'
 *
 * @param $format
 *   a format string
 * @param $granularity
 *   an array of allowed date parts, all others will be removed
 *   array('year', 'month', 'day', 'hour', 'minute', 'second');
 * @return
 *   a format string with all other elements removed
 */
function date_limit_format($format, $granularity) {
  // If punctuation has been escaped, remove the escaping.
  // Done using strtr because it is easier than getting the
  // escape character extracted using preg_replace.
  $replace = array(
    '\-' => '-',
    '\:' => ':',
    "\'" => "'",
    '\. ' => ' . ',
    '\,' => ',',
    );
  $format = strtr($format, $replace); 

  // Get the 'T' out of ISO date formats that don't have
  // both date and time.
  if (!date_has_time($granularity) || !date_has_date($granularity)) {
    $format = str_replace('\T', ' ', $format);
    $format = str_replace('T', ' ', $format);
  }

  $regex = array();
  if (!date_has_time($granularity)) {
    $regex[] = '((?<!\\\\)[a|A])';
  }
  // Create regular expressions to remove selected values from string.
  // Use (?<!\\\\) to keep escaped letters from being removed.
  foreach (date_nongranularity($granularity) as $element) {
    switch ($element) {
      case 'year':
        $regex[] = '([\-/\.,:]?\s?(?<!\\\\)[Yy])';
        break;
      case 'day':
        $regex[] = '([\-/\.,:]?\s?(?<!\\\\)[l|D|d|dS|j|jS]{1,2})';
        break;
      case 'month':
        $regex[] = '([\-/\.,:]?\s?(?<!\\\\)[FMmn])';
        break;
      case 'hour':
        $regex[] = '([\-/\.,:]?\s?(?<!\\\\)[HhGg])';
        break;
      case 'minute':
        $regex[] = '([\-/\.,:]?\s?(?<!\\\\)[i])';
        break;
      case 'second':
        $regex[] = '([\-/\.,:]?\s?(?<!\\\\)[s])';
        break;
      case 'timezone':
        $regex[] = '([\-/\.,:]?\s?(?<!\\\\)[TOZPe])';
        break;

    }
  }
  // Remove empty parentheses, brackets, pipes.
  $regex[] = '(\(\))';
  $regex[] = '(\[\])';
  $regex[] = '(\|\|)';

  // Remove selected values from string.
  $format = trim(preg_replace($regex, array(), $format));
  // Remove orphaned punctuation at the beginning of the string.
  $format = preg_replace('`^([\-/\.,:\'])`', '', $format);
  // Remove orphaned punctuation at the end of the string.
  $format = preg_replace('([\-/\.,:\']$)', '', $format);
  $format = preg_replace('(\\$)', '', $format);

  // Trim any whitespace from the result.
  $format = trim($format);

  // After removing the non-desired parts of the format, test if the only 
  // things left are escaped, non-date, characters. If so, return nothing.
  if (!$test = trim(preg_replace('(\\\\\w{1})', '', $format))) {
    return '';
  }
  return $format;
}

/**
 * Convert a format to an ordered array of granularity parts.
 *
 * Example:
 *   date_format_order('m/d/Y H:i')
 *   returns
 *     array(
 *       0 => 'month',
 *       1 => 'day',
 *       2 => 'year',
 *       3 => 'hour',
 *       4 => 'minute',
 *     );
 *
 * @param string $format
 * @return array of ordered granularity elements in this format string
 */
function date_format_order($format) {
  $order = array();
  if (empty($format)) return $order;
  $max = strlen($format);
  for ($i = 0; $i <= $max; $i++) {
    if (!isset($format[$i])) break;
    $c = $format[$i];
    switch ($c) {
      case 'd':
      case 'j':
        $order[] = 'day';
        break;
      case 'F':
      case 'M':
      case 'm':
      case 'n':
        $order[] = 'month';
        break;
      case 'Y':
      case 'y':
        $order[] = 'year';
        break;
      case 'g':
      case 'G':
      case 'h':
      case 'H':
        $order[] = 'hour';
        break;
      case 'i':
        $order[] = 'minute';
        break;
      case 's':
        $order[] = 'second';
        break;
    }
  }
  return $order;
}

/**
 * An difference array of granularity elements that are NOT in the
 * granularity array. Used by functions that strip unwanted
 * granularity elements out of formats and values.
 *
 * @param $granularity
 *   an array like ('year', 'month', 'day', 'hour', 'minute', 'second');
 */
function date_nongranularity($granularity) {
  return array_diff(array('year', 'month', 'day', 'hour', 'minute', 'second', 'timezone'), (array) $granularity);
}

/**
 * Implement hook_element_info().
 */
function date_api_element_info() {
  module_load_include('inc', 'date_api', 'date_api_elements');
  return _date_api_element_info();
}

function date_api_theme() {
  $path = drupal_get_path('module', 'date_api');
  $base = array(
    'file' => 'theme.inc',
    'path' => "$path/theme",
  );
  return array(
    'date_nav_title' => $base + array('variables' => array('granularity' => NULL, 'view' => NULL, 'link' => NULL, 'format' => NULL)),
    'date_vcalendar' => $base + array('variables' => array('events' => NULL, 'calname' => NULL)),
    'date_vevent' => $base + array('variables' => array('event' => NULL)),
    'date_valarm' => $base + array('variables' => array('alarm' => NULL)),
    'date_timezone' => $base + array('render element' => 'element'),
    'date_select' => $base + array('render element' => 'element'),
    'date_text' => $base + array('render element' => 'element'),
    'date_select_element' => $base + array('render element' => 'element'),
    'date_textfield_element' => $base + array('render element' => 'element'),
    'date_date_part_hour_prefix' => $base + array('render element' => 'element'),
    'date_part_minsec_prefix' => $base + array('render element' => 'element'),
    'date_part_label_year' => $base + array('variables' => array('date_part' => NULL, 'element' => NULL)),
    'date_part_label_month' => $base + array('variables' => array('date_part' => NULL, 'element' => NULL)),
    'date_part_label_day' => $base + array('variables' => array('date_part' => NULL, 'element' => NULL)),
    'date_part_label_hour' => $base + array('variables' => array('date_part' => NULL, 'element' => NULL)),
    'date_part_label_minute' => $base + array('variables' => array('date_part' => NULL, 'element' => NULL)),
    'date_part_label_second' => $base + array('variables' => array('date_part' => NULL, 'element' => NULL)),
    'date_part_label_ampm' => $base + array('variables' => array('date_part' => NULL, 'element' => NULL)),
    'date_part_label_timezone' => $base + array('variables' => array('date_part' => NULL, 'element' => NULL)),
    'date_views_filter_form' => $base + array('template' => 'date-views-filter-form', 'render element' => 'form'),
    'date_calendar_day' => $base + array('variables' => array('date' => NULL)),  
    'date_time_ago' => $base + array('variables' => array('start_date' => NULL, 'end_date' => NULL, 'interval' => NULL))
  );
}

/**
 * Wrapper around date handler setting for timezone.
 */
function date_api_set_db_timezone($offset = '+00:00') {
  module_load_include('inc', 'date_api', 'date_api_sql');
  $handler = new date_sql_handler();
  return $handler->set_db_timezone($offset);
}

/**
 *  Function to figure out which local timezone applies to a date and select it
 */
function date_get_timezone($handling, $timezone = '') {
  switch ($handling) {
    case ('date'):
      $timezone = !empty($timezone) ? $timezone : date_default_timezone();
      break;
    case ('utc'):
      $timezone = 'UTC';
      break;
    default:
      $timezone = date_default_timezone();
  }
  return $timezone > '' ? $timezone : date_default_timezone();
}

/**
 *  Function to figure out which db timezone applies to a date and select it
 */
function date_get_timezone_db($handling, $timezone = '') {
  switch ($handling) {
    case ('none'):
      $timezone = date_default_timezone();
      break;
    default:
      $timezone = 'UTC';
      break;
  }
  return $timezone > '' ? $timezone : 'UTC';
}
/**
 * Helper function for BYDAY options in Date Repeat
 * and for converting back and forth from '+1' to 'First' .
 */
function date_order_translated() {
  return array(
    '+1' => t('First', array(), array('context' => 'date_order')),
    '+2' => t('Second', array(), array('context' => 'date_order')),
    '+3' => t('Third', array(), array('context' => 'date_order')),
    '+4' => t('Fourth', array(), array('context' => 'date_order')),
    '+5' => t('Fifth', array(), array('context' => 'date_order')),
    '-1' => t('Last', array(), array('context' => 'date_order_reverse')),
    '-2' => t('Next to last', array(), array('context' => 'date_order_reverse')),
    '-3' => t('Third from last', array(), array('context' => 'date_order_reverse')),
    '-4' => t('Fourth from last', array(), array('context' => 'date_order_reverse')),
    '-5' => t('Fifth from last', array(), array('context' => 'date_order_reverse'))
  );
}

function date_order() {
  return array(
    '+1' => 'First',
    '+2' => 'Second',
    '+3' => 'Third',
    '+4' => 'Fourth',
    '+5' => 'Fifth',
    '-1' => 'Last',
    '-2' => '-2',
    '-3' => '-3',
    '-4' => '-4',
    '-5' => '-5'
  );
}

/*
 * Test validity of a date range string.
 */
function date_range_valid($string) {
  $matches = preg_match('@\-[0-9]*:[\+|\-][0-9]*@', $string);
  return $matches < 1 ? FALSE : TRUE;
}

/**
 * Split a string like -3:+3 or 2001:2010 into 
 * an array of min and max years.
 * 
 * Center the range around the current year, if any, but expand it far
 * enough so it will pick up the year value in the field in case
 * the value in the field is outside the initial range.
 */
function date_range_years($string, $date = NULL) {
  $this_year = date_format(date_now(), 'Y');
  list($min_year, $max_year) = explode(':', $string);

  // Valid patterns would be -5:+5, 0:+1, 2008:2010.
  $plus_pattern = '@[\+|\-][0-9]{1,4}@';
  $year_pattern = '@[0-9]{4}@';
  if (!preg_match($year_pattern, $min_year, $matches)) {
    if (preg_match($plus_pattern, $min_year, $matches)) {
      $min_year = $this_year + $matches[0];
    }
    else {
      $min_year = $this_year;
    }
  }
  if (!preg_match($year_pattern, $max_year, $matches)) {
    if (preg_match($plus_pattern, $max_year, $matches)) {
      $max_year = $this_year + $matches[0];
    }
    else {
      $max_year = $this_year;
    }
  }
  // We expect the $min year to be less than the $max year.
  // Some custom values for -99:+99 might not obey that.
  if ($min_year > $max_year) {
    $temp = $max_year;
    $max_year = $min_year;
    $min_year = $temp;
  }
  // If there is a current value, stretch the range to include it.
  $value_year = is_object($date) ? $date->format('Y') : '';
  if (!empty($value_year)) {
    $min_year = min($value_year, $min_year);
    $max_year = max($value_year, $max_year);
  }
  return array($min_year, $max_year);
}

/**
 * Convert a min and max year into a string like '-3:+1' .
 *
 * @param unknown_type $years
 * @return unknown
 */
function date_range_string($years) {
  $this_year = date_format(date_now(), 'Y');
  if ($years[0] < $this_year) {
    $min = '-' . ($this_year - $years[0]);
  }
  else {
    $min = '+' . ($years[0] - $this_year);
  }
  if ($years[1] < $this_year) {
    $max = '-' . ($this_year - $years[1]);
  }
  else {
    $max = '+' . ($years[1] - $this_year);
  }
  return $min . ':' . $max;
}
/**
 * Temporary helper to re-create equivalent 
 * of content_database_info.
 */
function date_api_database_info($field) {
  $data = $field['storage']['details']['sql'][FIELD_LOAD_CURRENT];
  $db_info = array('columns' => $data);
  $current_table = _field_sql_storage_tablename($field);
  $revision_table = _field_sql_storage_revision_tablename($field);
  $db_info['table'] = $current_table;
  return $db_info;
}

/**
 * Implementation of hook_form_alter().
 *
 * Add a form element to configure whether or not week numbers are ISO-8601 (default: FALSE == US/UK/AUS norm).
 */
function date_api_form_system_regional_settings_alter(&$form, $form_state, $form_id = 'system_date_time_settings') {
  $form['locale']['date_api_use_iso8601'] = array(
    '#type'          => 'checkbox',
    '#title'         => t('Use ISO-8601 week numbers'),
    '#default_value' => variable_get('date_api_use_iso8601', FALSE),
    '#description'   => t('IMPORTANT! If checked, First day of week MUST be set to Monday'),
  );
  $form['#validate'][] = 'date_api_form_system_settings_validate';
  $form = system_settings_form($form);
}

/**
 * Validate that the option to use ISO weeks matches first day of week choice.
 */
function date_api_form_system_settings_validate(&$form, &$form_state) {
  $form_values = $form_state['values'];
  if ($form_values['date_api_use_iso8601'] && $form_values['date_first_day'] != 1) {
    form_set_error('date_first_day', t('When using ISO-8601 week numbers, the first day of the week must be set to Monday.'));
  }
}

function date_format_type_options() {
  $options = array();
  $format_types = system_get_date_types();
  if (!empty($format_types)) {
    foreach ($format_types as $type => $type_info) {
      $options[$type] = $type_info['title'];
    }
  }
  return $options;
}

/**
 * Determine if a from/to date combination qualify as 'All day'.
 *
 * @param object $date1, a string date in datetime format for the 'from' date.
 * @param object $date2, a string date in datetime format for the 'to' date.
 * @return TRUE or FALSE.
 */
function date_is_all_day($string1, $string2, $granularity = 'second', $increment = 1) {
  if (empty($string1) || empty($string2)) {
    return FALSE;
  }
  elseif (!in_array($granularity, array('hour', 'minute', 'second'))) {
    return FALSE;
  }

  preg_match('/([0-9]{4}-[0-9]{2}-[0-9]{2}) (([0-9]{2}):([0-9]{2}):([0-9]{2}))/', $string1, $matches);
  $count = count($matches);
  $date1 = $count > 1 ? $matches[1] : '';
  $time1 = $count > 2 ? $matches[2] : '';
  $hour1 = $count > 3 ? intval($matches[3]) : 0;
  $min1 = $count > 4 ? intval($matches[4]) : 0;
  $sec1 = $count > 5 ? intval($matches[5]) : 0;
  preg_match('/([0-9]{4}-[0-9]{2}-[0-9]{2}) (([0-9]{2}):([0-9]{2}):([0-9]{2}))/', $string2, $matches);
  $count = count($matches);
  $date2 = $count > 1 ? $matches[1] : '';
  $time2 = $count > 2 ? $matches[2] : '';
  $hour2 = $count > 3 ? intval($matches[3]) : 0;
  $min2 = $count > 4 ? intval($matches[4]) : 0;
  $sec2 = $count > 5 ? intval($matches[5]) : 0;

  if (empty($date1) || empty($date2)) {
    return FALSE;
  }
  if (empty($time1) || empty($time2)) {
    return FALSE;
  }

  $tmp = date_seconds('s', TRUE, $increment);
  $max_seconds = intval(array_pop($tmp));
  $tmp = date_minutes('i', TRUE, $increment);
  $max_minutes = intval(array_pop($tmp));

  switch ($granularity) {
    case 'second':
      $min_match = $time1 == '00:00:00' || ($hour1 == 0 && $min1 == 0 && $sec1 == 0);
      $max_match = $time2 == '00:00:00' || ($hour2 == 23 && $min2 == $max_minutes && $sec2 == $max_seconds) || ($hour1 == 0 && $hour2 == 0 && $min1 == 0 && $min2 == 0 && $sec1 == 0 && $sec2 == 0);
      break;
    case 'minute':
      $min_match = $time1 == '00:00:00' || ($hour1 == 0 && $min1 == 0);
      $max_match = $time2 == '00:00:00' || ($hour2 == 23 && $min2 == $max_minutes) || ($hour1 == 0 && $hour2 == 0 && $min1 == 0 && $min2 == 0);
      break;
    case 'hour':
      $min_match = $time1 == '00:00:00' || ($hour1 == 0);
      $max_match = $time2 == '00:00:00' || ($hour2 == 23) || ($hour1 == 0 && $hour2 == 0);
      break;
    default:
      $min_match = TRUE;
      $max_match = FALSE;
  }

  if ($min_match && $max_match) {
    return TRUE;
  }

  return FALSE;
}

/**
 * Helper function to round minutes and seconds to requested value.
 */
function date_increment_round(&$date, $increment) {
  // Round minutes and seconds, if necessary.
  if (is_object($date) && $increment > 1) {
    $day = intval(date_format($date, 'j'));
    $hour = intval(date_format($date, 'H'));
    $second = intval(round(intval(date_format($date, 's')) / $increment) * $increment);
    $minute = intval(date_format($date, 'i'));
    if ($second == 60) {
      $minute += 1;
      $second = 0;
    }
    $minute = intval(round($minute / $increment) * $increment);
    if ($minute == 60) {
      $hour += 1;
      $minute = 0;
    }
    date_time_set($date, $hour, $minute, $second);
    if ($hour == 24) {
      $day += 1;
      $hour = 0;
      $year = date_format($date, 'Y');
      $month = date_format($date, 'n');
      date_date_set($date, $year, $month, $day);
    }
  }
  return $date;
}

/**
 * Return the nested form elements for a field by name.
 * This can be used either to retrieve the entire sub-element
 * for a field by name, no matter how deeply nested it is within
 * fieldgroups or multigroups, or to find the multiple value
 * sub-elements within a field element by name (i.e. 'value' or
 * 'rrule'). You can also use this function to see if an item exists 
 * in a form (the return will be an empty array if it does not exist).
 *
 * The function returns an array of results. A field will generally
 * only exist once in a form but the function can also be used to 
 * locate all the 'value' elements within a multiple value field,
 * so the result is always returned as an array of values.
 *
 * For example, for a field named field_custom,  the following will 
 * pluck out the form elements for this field from the node form, 
 * no matter how deeply it is nested within fieldgroups or fieldsets:
 *
 * $elements = content_get_nested_elements($node_form, 'field_custom');
 *
 * You can prefix the function with '&' to retrieve the element by 
 * reference to alter it directly:
 *
 * $elements = &content_get_nested_elements($form, 'field_custom');
 * foreach ($elements as $element) {
 *   $element['#after_build'][] = 'my_field_afterbuild';
 * }
 *
 * During the #after_build you could then do something like the
 * following to alter each individual part of a multiple value field:
 *
 * $sub_elements = &content_get_nested_elements($element, 'value');
 * foreach ($sub_elements as $sub_element) {
 *   $sub_element['#element_validate'][] = 'custom_validation';
 * }
 *
 * @param $form
 *   The form array to search.
 * @param $field_name
 *   The name or key of the form elements to return.
 * @return
 *   An array of all matching form elements, returned by reference.
 */
function &date_get_nested_elements(&$form, $field_name) {
  $elements = array();

  foreach (element_children($form) as $key) {
    if ($key === $field_name) {
      $elements[] = &$form[$key];
    }
    else if (is_array($form[$key])) {
      $nested_form = &$form[$key];
      if ($sub_elements = &date_get_nested_elements($nested_form, $field_name)) {
        $elements = array_merge($elements, $sub_elements);
      }
    }
  }

  return $elements;
}