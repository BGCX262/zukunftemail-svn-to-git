<?php
/**
 * EDateiCal class file.
 *
 * @author Jon Doe <jonny@gmail.com>
 * @link http://www.yiiframework.com/
 * @copyright Copyright &copy; 2008-2011 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

/**
 * EDateiCal is ...
 *
 *
 * @author Jon Doe <jonny@gmail.com>
 * @version
 * @package
 * @since 1.0
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
class EDateiCal extends CApplicationComponent
{
	public $date_first_day = 1;
	/**
	 * Initializes the application component.
	 * This method is required by {@link IApplicationComponent} and is invoked by application.
	 * If you override this method, make sure to call the parent implementation
	 * so that the application component can be marked as initialized.
	 */
	public function init()
	{
		parent::init();
		// your code...
	}

	/**
	 * Logs a message.
	 *
	 * @param string $message Message to be logged
	 * @param string $level Level of the message (e.g. 'trace', 'warning',
	 * 'error', 'info', see CLogger constants definitions)
	 */
	public static function log($message, $level='error')
	{
		Yii::log($message, $level, __CLASS__);
	}

	/**
	 * Dumps a variable or the object itself in terms of a string.
	 *
	 * @param mixed variable to be dumped
	 */
	protected function dump($var='dump-the-object',$highlight=true)
	{
		if ($var === 'dump-the-object') {
			return CVarDumper::dumpAsString($this,$depth=15,$highlight);
		} else {
			return CVarDumper::dumpAsString($var,$depth=15,$highlight);
		}
	}

	/**
	 * Return an array of iCalendar information from an iCalendar file.
	 *
	 *   No timezone adjustment is performed in the import since the timezone
	 *   conversion needed will vary depending on whether the value is being
	 *   imported into the database (when it needs to be converted to UTC), is being
	 *   viewed on a site that has user-configurable timezones (when it needs to be
	 *   converted to the user's timezone), if it needs to be converted to the
	 *   site timezone, or if it is a date without a timezone which should not have
	 *   any timezone conversion applied.
	 *
	 *   Properties that have dates and times are converted to sub-arrays like:
	 *      'datetime'   => date in YYYY-MM-DD HH:MM format, not timezone adjusted
	 *      'all_day'    => whether this is an all-day event
	 *      'tz'         => the timezone of the date, could be blank for absolute
	 *                      times that should get no timezone conversion.
	 *
	 *   Exception dates can have muliple values and are returned as arrays
	 *   like the above for each exception date.
	 *
	 *   Most other properties are returned as PROPERTY => VALUE.
	 *
	 *   Each item in the VCALENDAR will return an array like:
	 *   [0] => Array (
	 *     [TYPE] => VEVENT
	 *     [UID] => 104
	 *     [SUMMARY] => An example event
	 *     [URL] => http://example.com/node/1
	 *     [DTSTART] => Array (
	 *       [datetime] => 1997-09-07 09:00:00
	 *       [all_day] => 0
	 *       [tz] => US/Eastern
	 *     )
	 *     [DTEND] => Array (
	 *       [datetime] => 1997-09-07 11:00:00
	 *       [all_day] => 0
	 *       [tz] => US/Eastern
	 *     )
	 *     [RRULE] => Array (
	 *       [FREQ] => Array (
	 *         [0] => MONTHLY
	 *       )
	 *       [BYDAY] => Array (
	 *         [0] => 1SU
	 *         [1] => -1SU
	 *       )
	 *     )
	 *     [EXDATE] => Array (
	 *       [0] = Array (
	 *         [datetime] => 1997-09-21 09:00:00
	 *         [all_day] => 0
	 *         [tz] => US/Eastern
	 *       )
	 *       [1] = Array (
	 *         [datetime] => 1997-10-05 09:00:00
	 *         [all_day] => 0
	 *         [tz] => US/Eastern
	 *       )
	 *     )
	 *     [RDATE] => Array (
	 *       [0] = Array (
	 *         [datetime] => 1997-09-21 09:00:00
	 *         [all_day] => 0
	 *         [tz] => US/Eastern
	 *       )
	 *       [1] = Array (
	 *         [datetime] => 1997-10-05 09:00:00
	 *         [all_day] => 0
	 *         [tz] => US/Eastern
	 *       )
	 *     )
	 *   )
	 *
	 * @param $filename
	 *   Location (local or remote) of a valid iCalendar file
	 * @return array
	 *   An array with all the elements from the ical
	 * @todo
	 *   figure out how to handle this if subgroups are nested,
	 *   like a VALARM nested inside a VEVENT.
	 */
	public static function date_ical_import($filename) {
		// Fetch the iCal data. If file is a URL, use drupal_http_request. fopen
		// isn't always configured to allow network connections.
		if (substr($filename, 0, 4) == 'http') {
			// Fetch the ical data from the specified network location
			$icaldatafetch = drupal_http_request($filename);
			// Check the return result
			if ($icaldatafetch->error) {
				Yii::log(Yii::t('app', 'HTTP Request Error importing %filename: @error', array('%filename' => $filename, '@error' => $icaldatafetch->error)));
				return FALSE;
			}
			// Break the return result into one array entry per lines
			$icaldatafolded = explode("\n", $icaldatafetch->data);
		}
		else {
			$icaldatafolded = @file($filename, FILE_IGNORE_NEW_LINES);
			if ($icaldatafolded === FALSE) {
				Yii::log('Failed to open file: $filename');
				return FALSE;
			}
		}
		// Verify this is iCal data
		if (trim($icaldatafolded[0]) != 'BEGIN:VCALENDAR') {
			Yii::log('Invalid calendar file: $filename', 'error');
			return FALSE;
		}
		return self::date_ical_parse($icaldatafolded);
	}

	/**
	 * Return an array of iCalendar information from an iCalendar file.
	 *
	 * As date_ical_import() but different param.
	 *
	 * @param $icaldatafolded
	 *   an array of lines from an ical feed.
	 * @return array
	 *   An array with all the elements from the ical.
	 */
	public static function date_ical_parse($icaldatafolded = array()) {
		$items = array();

		// Verify this is iCal data
		if (trim($icaldatafolded[0]) != 'BEGIN:VCALENDAR') {
			Yii::log('Invalid calendar file.', 'error');
			return FALSE;
		}

		// "unfold" wrapped lines
		$icaldata = array();
		foreach ($icaldatafolded as $line) {
			$out = array();
			// See if this looks like the beginning of a new property or value.
			// If not, it is a continuation of the previous line.
			// The regex is to ensure that wrapped QUOTED-PRINTABLE data
			// is kept intact.
			if (!preg_match('/([A-Z]+)[:;](.*)/', $line, $out)) {
				$line = array_pop($icaldata) . ($line);
			}
			$icaldata[] = $line;
		}
		unset($icaldatafolded);

		// Parse the iCal information
		$parents = array();
		$subgroups = array();
		$vcal = '';
		foreach ($icaldata as $line) {
			$line = trim($line);
			$vcal .= $line . "\n";
			// Deal with begin/end tags separately
			if (preg_match('/(BEGIN|END):V(\S+)/', $line, $matches)) {
				$closure = $matches[1];
				$type = 'V' . $matches[2];
				if ($closure == 'BEGIN') {
					array_push($parents, $type);
					array_push($subgroups, array());
				}
				elseif ($closure == 'END') {
					end($subgroups);
					$subgroup =& $subgroups[key($subgroups)];
					switch ($type) {
						case 'VCALENDAR':
							if (prev($subgroups) == FALSE) {
								$items[] = array_pop($subgroups);
							}
							else {
								$parent[array_pop($parents)][] = array_pop($subgroups);
							}
							break;
							// Add the timezones in with their index their TZID
						case 'VTIMEZONE':
							$subgroup = end($subgroups);
							$id = $subgroup['TZID'];
							unset($subgroup['TZID']);

							// Append this subgroup onto the one above it
							prev($subgroups);
							$parent =& $subgroups[key($subgroups)];

							$parent[$type][$id] = $subgroup;

							array_pop($subgroups);
							array_pop($parents);
							break;
							// Do some fun stuff with durations and all_day events
							// and then append to parent
						case 'VEVENT':
						case 'VALARM':
						case 'VTODO':
						case 'VJOURNAL':
						case 'VVENUE':
						case 'VFREEBUSY':
						default:
							// Can't be sure whether DTSTART is before or after DURATION,
							// so parse DURATION at the end.
							if (isset($subgroup['DURATION'])) {
								date_ical_parse_duration($subgroup, 'DURATION');
							}
							// Add a top-level indication for the 'All day' condition.
							// Leave it in the individual date components, too, so it
							// is always available even when you are working with only
							// a portion of the VEVENT array, like in Feed API parsers.
							$subgroup['all_day'] = FALSE;
							if (!empty($subgroup['DTSTART']) && (!empty($subgroup['DTSTART']['all_day']) ||
							(empty($subgroup['DTEND']) && empty($subgroup['RRULE']) && empty($subgroup['RRULE']['COUNT'])))) {
								// Many programs output DTEND for an all day event as the
								// following day, reset this to the same day for internal use.
								$subgroup['all_day'] = TRUE;
								$subgroup['DTEND'] = $subgroup['DTSTART'];
							}
							// Add this element to the parent as an array under the
							// component name
						default:
							prev($subgroups);
							$parent =& $subgroups[key($subgroups)];

							$parent[$type][] = $subgroup;

							array_pop($subgroups);
							array_pop($parents);
							break;
					}
				}
			}
			// Handle all other possibilities
			else {
				// Grab current subgroup
				end($subgroups);
				$subgroup =& $subgroups[key($subgroups)];

				// Split up the line into nice pieces for PROPERTYNAME,
				// PROPERTYATTRIBUTES, and PROPERTYVALUE
				preg_match('/([^;:]+)(?:;([^:]*))?:(.+)/', $line, $matches);
				$name = !empty($matches[1]) ? strtoupper(trim($matches[1])) : '';
				$field = !empty($matches[2]) ? $matches[2] : '';
				$data = !empty($matches[3]) ? $matches[3] : '';
				$parse_result = '';
				switch ($name) {
					// Keep blank lines out of the results.
					case '':
						break;

						// Lots of properties have date values that must be parsed out.
					case 'CREATED':
					case 'LAST-MODIFIED':
					case 'DTSTART':
					case 'DTEND':
					case 'DTSTAMP':
					case 'FREEBUSY':
					case 'DUE':
					case 'COMPLETED':
						$parse_result = self::date_ical_parse_date($field, $data);
						break;

					case 'EXDATE':
					case 'RDATE':
						$parse_result = self::date_ical_parse_exceptions($field, $data);
						break;

					case 'TRIGGER':
						// A TRIGGER can either be a date or in the form -PT1H.
						if (!empty($field)) {
							$parse_result = self::date_ical_parse_date($field, $data);
						}
						else {
							$parse_result = array('DATA' => $data);
						}
						break;

					case 'DURATION':
						// Can't be sure whether DTSTART is before or after DURATION in
						// the VEVENT, so store the data and parse it at the end.
						$parse_result = array('DATA' => $data);
						break;

					case 'RRULE':
					case 'EXRULE':
						$parse_result = self::date_ical_parse_rrule($field, $data);
						break;

					case 'SUMMARY':
					case 'DESCRIPTION':
						$parse_result = self::date_ical_parse_text($field, $data);
						break;

					case 'LOCATION':
						$parse_result = self::date_ical_parse_location($field, $data);
						break;

						// For all other properties, just store the property and the value.
						// This can be expanded on in the future if other properties should
						// be given special treatment.
					default:
						$parse_result = $data;
						break;
				}

				// Store the result of our parsing
				$subgroup[$name] = $parse_result;
			}
		}
		return $items;
	}

	/**
	 * Parse a ical date element.
	 *
	 * Possible formats to parse include:
	 *   PROPERTY:YYYYMMDD[T][HH][MM][SS][Z]
	 *   PROPERTY;VALUE=DATE:YYYYMMDD[T][HH][MM][SS][Z]
	 *   PROPERTY;VALUE=DATE-TIME:YYYYMMDD[T][HH][MM][SS][Z]
	 *   PROPERTY;TZID=XXXXXXXX;VALUE=DATE:YYYYMMDD[T][HH][MM][SS]
	 *   PROPERTY;TZID=XXXXXXXX:YYYYMMDD[T][HH][MM][SS]
	 *
	 *   The property and the colon before the date are removed in the import
	 *   process above and we are left with $field and $data.
	 *
	 *  @param $field
	 *    The text before the colon and the date, i.e.
	 *    ';VALUE=DATE:', ';VALUE=DATE-TIME:', ';TZID='
	 *  @param $data
	 *    The date itself, after the colon, in the format YYYYMMDD[T][HH][MM][SS][Z]
	 *    'Z', if supplied, means the date is in UTC.
	 *
	 *  @return array
	 *   $items array, consisting of:
	 *      'datetime'   => date in YYYY-MM-DD HH:MM format, not timezone adjusted
	 *      'all_day'    => whether this is an all-day event with no time
	 *      'tz'         => the timezone of the date, could be blank if the ical
	 *                      has no timezone; the ical specs say no timezone
	 *                      conversion should be done if no timezone info is
	 *                      supplied
	 *  @todo
	 *   Another option for dates is the format PROPERTY;VALUE=PERIOD:XXXX. The period
	 *   may include a duration, or a date and a duration, or two dates, so would
	 *   have to be split into parts and run through date_ical_parse_date() and
	 *   date_ical_parse_duration(). This is not commonly used, so ignored for now.
	 *   It will take more work to figure how to support that.
	 */
	public static function date_ical_parse_date($field, $data) {
		$items = array('datetime' => '', 'all_day' => '', 'tz' => '');
		if (empty($data)) {
			return $items;
		}
		// Make this a little more whitespace independent
		$data = trim($data);

		// Turn the properties into a nice indexed array of
		// array(PROPERTYNAME => PROPERTYVALUE);
		$field_parts = preg_split('/[;:]/', $field);
		$properties = array();
		foreach ($field_parts as $part) {
			if (strpos($part, '=') !== FALSE) {
				$tmp = explode('=', $part);
				$properties[$tmp[0]] = $tmp[1];
			}
		}

		// Make this a little more whitespace independent
		$data = trim($data);

		// Record if a time has been found
		$has_time = FALSE;

		// If a format is specified, parse it according to that format
		if (isset($properties['VALUE'])) {
			switch ($properties['VALUE']) {
				case 'DATE':
					preg_match(DATE_REGEX_ICAL_DATE, $data, $regs);
					$datetime = self::date_pad($regs[1]) . '-' . self::date_pad($regs[2]) . '-' . self::date_pad($regs[3]); // Date
					break;
				case 'DATE-TIME':
					preg_match(DATE_REGEX_ICAL_DATETIME, $data, $regs);
					$datetime = self::date_pad($regs[1]) . '-' . self::date_pad($regs[2]) . '-' . self::date_pad($regs[3]); // Date
					$datetime .= ' ' . self::date_pad($regs[4]) . ':' . self::date_pad($regs[5]) . ':' . self::date_pad($regs[6]); // Time
					$has_time = TRUE;
					break;
			}
		}
		// If no format is specified, attempt a loose match
		else {
			preg_match(DATE_REGEX_LOOSE, $data, $regs);
			if (!empty($regs) && count($regs) > 2) {
				$datetime = self::date_pad($regs[1]) . '-' . self::date_pad($regs[2]) . '-' . self::date_pad($regs[3]); // Date
				if (isset($regs[4])) {
					$has_time = TRUE;
					$datetime .= ' ' . (!empty($regs[5]) ? self::date_pad($regs[5]) : '00') .
         ':' . (!empty($regs[6]) ? self::date_pad($regs[6]) : '00') .
         ':' . (!empty($regs[7]) ? self::date_pad($regs[7]) : '00'); // Time
				}
			}
		}

		// Use timezone if explicitly declared
		if (isset($properties['TZID'])) {
			$tz = $properties['TZID'];
			// Fix commonly used alternatives like US-Eastern which should be US/Eastern.
			$tz = str_replace('-', '/', $tz);
			// Unset invalid timezone names.
			$tz = self::_date_timezone_replacement($tz);
			if (!self::date_timezone_is_valid($tz)) {
				$tz = '';
			}
		}
		// If declared as UTC with terminating 'Z', use that timezone
		elseif (strpos($data, 'Z') !== FALSE) {
			$tz = 'UTC';
		}
		// Otherwise this date is floating...
		else {
			$tz = '';
		}

		$items['datetime'] = $datetime;
		$items['all_day'] = $has_time ? FALSE : TRUE;
		$items['tz'] = $tz;
		return $items;
	}

	/**
	 * Parse an ical repeat rule.
	 *
	 * @return array
	 *   Array in the form of PROPERTY => array(VALUES)
	 *   PROPERTIES include FREQ, INTERVAL, COUNT, BYDAY, BYMONTH, BYYEAR, UNTIL
	 */
	public static function date_ical_parse_rrule($field, $data) {
		$data = preg_replace("/RRULE.*:/", '', $data);
		$items = array('DATA' => $data);
		$rrule = explode(';', $data);
		foreach ($rrule as $key => $value) {
			$param = explode('=', $value);
			// Must be some kind of invalid data.
			if (count($param) != 2) {
				continue;
			}
			if ($param[0] == 'UNTIL') {
				$values = self::date_ical_parse_date('', $param[1]);
			}
			else {
				$values = explode(',', $param[1]);
			}
			// Different treatment for items that can have multiple values and those that cannot.
			if (in_array($param[0], array('FREQ', 'INTERVAL', 'COUNT', 'WKST'))) {
				$items[$param[0]] = $param[1];
			}
			else {
				$items[$param[0]] = $values;
			}
		}
		return $items;
	}

	/**
	 * Parse exception dates (can be multiple values).
	 *
	 * @return array
	 *   an array of date value arrays.
	 */
	public static function date_ical_parse_exceptions($field, $data) {
		$data = str_replace($field.':', '', $data);
		$items = array('DATA' => $data);
		$ex_dates = explode(',', $data);
		foreach ($ex_dates as $ex_date) {
			$items[] = self::date_ical_parse_date('', $ex_date);
		}
		return $items;
	}

	/**
	 * Parse the duration of the event.
	 * Example:
	 *  DURATION:PT1H30M
	 *  DURATION:P1Y2M
	 *
	 *  @param $subgroup
	 *   array of other values in the vevent so we can check for DTSTART
	 */
	public static function date_ical_parse_duration(&$subgroup, $field = 'DURATION') {
		$items = $subgroup[$field];
		$data  = $items['DATA'];
		preg_match('/^P(\d{1,4}[Y])?(\d{1,2}[M])?(\d{1,2}[W])?(\d{1,2}[D])?([T]{0,1})?(\d{1,2}[H])?(\d{1,2}[M])?(\d{1,2}[S])?/', $data, $duration);
		$items['year'] = isset($duration[1]) ? str_replace('Y', '', $duration[1]) : '';
		$items['month'] = isset($duration[2]) ?str_replace('M', '', $duration[2]) : '';
		$items['week'] = isset($duration[3]) ?str_replace('W', '', $duration[3]) : '';
		$items['day'] = isset($duration[4]) ?str_replace('D', '', $duration[4]) : '';
		$items['hour'] = isset($duration[6]) ?str_replace('H', '', $duration[6]) : '';
		$items['minute'] = isset($duration[7]) ?str_replace('M', '', $duration[7]) : '';
		$items['second'] = isset($duration[8]) ?str_replace('S', '', $duration[8]) : '';
		$start_date = array_key_exists('DTSTART', $subgroup) ? $subgroup['DTSTART']['datetime'] : date_format(date_now(), DATE_FORMAT_ISO);
		$timezone = array_key_exists('DTSTART', $subgroup) ? $subgroup['DTSTART']['tz'] : variable_get('date_default_timezone');
		if (empty($timezone)) {
			$timezone = 'UTC';
		}
		$date = new DateObject($start_date, $timezone);
		$date2 = clone($date);
		foreach ($items as $item => $count) {
			if ($count > 0) {
				date_modify($date2, '+' . $count . ' ' . $item);
			}
		}
		$format = isset($subgroup['DTSTART']['type']) && $subgroup['DTSTART']['type'] == 'DATE' ? 'Y-m-d' : 'Y-m-d H:i:s';
		$subgroup['DTEND'] = array(
    'datetime' => date_format($date2, DATE_FORMAT_DATETIME),
    'all_day' => isset($subgroup['DTSTART']['all_day']) ? $subgroup['DTSTART']['all_day'] : 0,
    'tz' => $timezone,
		);
		$duration = date_format($date2, 'U') - date_format($date, 'U');
		$subgroup['DURATION'] = array('DATA' => $data, 'DURATION' => $duration);
	}

	/**
	 * Parse and clean up ical text elements.
	 */
	public static function date_ical_parse_text($field, $data) {
		if (strstr($field, 'QUOTED-PRINTABLE')) {
			$data = quoted_printable_decode($data);
		}
		// Strip line breaks within element
		$data = str_replace(array("\r\n ", "\n ", "\r "), '', $data);
		// Put in line breaks where encoded
		$data = str_replace(array("\\n", "\\N"), "\n", $data);
		// Remove other escaping
		$data = stripslashes($data);
		return $data;
	}

	/**
	 * Parse location elements.
	 *
	 * Catch situations like the upcoming.org feed that uses
	 * LOCATION;VENUE-UID="http://upcoming.yahoo.com/venue/104/":111 First Street...
	 * or more normal LOCATION;UID=123:111 First Street...
	 * Upcoming feed would have been improperly broken on the ':' in http://
	 * so we paste the $field and $data back together first.
	 *
	 * Use non-greedy check for ':' in case there are more of them in the address.
	 */
	public static function date_ical_parse_location($field, $data) {
		if (preg_match('/UID=[?"](.+)[?"][*?:](.+)/', $field . ':' . $data, $matches)) {
			$location = array();
			$location['UID'] = $matches[1];
			$location['DESCRIPTION'] = stripslashes($matches[2]);
			return $location;
		}
		else {
			// Remove other escaping
			$location = stripslashes($data);
			return $location;
		}
	}

	/**
	 * Return a date object for the ical date, adjusted to its local timezone.
	 *
	 *  @param $ical_date
	 *    an array of ical date information created in the ical import.
	 *  @param $to_tz
	 *    the timezone to convert the date's value to.
	 *  @return object
	 *    a timezone-adjusted date object
	 */
	public static function date_ical_date($ical_date, $to_tz = FALSE) {
		// If the ical date has no timezone, must assume it is stateless
		// so treat it as a local date.
		if (empty($ical_date['datetime'])) {
			return NULL;
		}
		elseif (empty($ical_date['tz'])) {
			$from_tz = self::date_default_timezone();
		}
		else {
			$from_tz = $ical_date['tz'];
		}
		$date = new DateObject($ical_date['datetime'], new DateTimeZone($from_tz));
		if ($to_tz && $ical_date['tz'] != '' && $to_tz != $ical_date['tz']) {
			date_timezone_set($date, timezone_open($to_tz));
		}
		return $date;
	}

	/**
	 * Escape #text elements for safe iCal use
	 *
	 * @param $text
	 *   Text to escape
	 *
	 * @return
	 *   Escaped text
	 *
	 */
	public static function date_ical_escape_text($text) {
		$text = drupal_html_to_text($text);
		$text = trim($text);
		// TODO Per #38130 the iCal specs don't want : and " escaped
		// but there was some reason for adding this in. Need to watch
		// this and see if anything breaks.
		//$text = str_replace('"', '\"', $text);
		//$text = str_replace(":", "\:", $text);
		$text = preg_replace("/\\\b/","\\\\", $text);
		$text = str_replace(",", "\,", $text);
		$text = str_replace(";", "\;", $text);
		$text = str_replace("\n", "\\n ", $text);
		return trim($text);
	}

	/**
	 * Build an iCal RULE from $form_values.
	 *
	 * @param $form_values
	 *   an array constructed like the one created by date_ical_parse_rrule()
	 *
	 *     [RRULE] => Array (
	 *       [FREQ] => Array (
	 *         [0] => MONTHLY
	 *       )
	 *       [BYDAY] => Array (
	 *         [0] => 1SU
	 *         [1] => -1SU
	 *       )
	 *       [UNTIL] => Array (
	 *         [datetime] => 1997-21-31 09:00:00
	 *         [all_day] => 0
	 *         [tz] => US/Eastern
	 *       )
	 *     )
	 *     [EXDATE] => Array (
	 *       [0] = Array (
	 *         [datetime] => 1997-09-21 09:00:00
	 *         [all_day] => 0
	 *         [tz] => US/Eastern
	 *       )
	 *       [1] = Array (
	 *         [datetime] => 1997-10-05 09:00:00
	 *         [all_day] => 0
	 *         [tz] => US/Eastern
	 *       )
	 *     )
	 *     [RDATE] => Array (
	 *       [0] = Array (
	 *         [datetime] => 1997-09-21 09:00:00
	 *         [all_day] => 0
	 *         [tz] => US/Eastern
	 *       )
	 *       [1] = Array (
	 *         [datetime] => 1997-10-05 09:00:00
	 *         [all_day] => 0
	 *         [tz] => US/Eastern
	 *       )
	 *     )
	 *
	 */
	public static function date_api_ical_build_rrule($form_values) {
		$RRULE = '';
		if (empty($form_values) || !is_array($form_values)) {
			return $RRULE;
		}

		//grab the RRULE data and put them into iCal RRULE format
		$RRULE .= 'RRULE:FREQ=' . (!array_key_exists('FREQ', $form_values) ? 'DAILY' : $form_values['FREQ']);
		$RRULE .= ';INTERVAL=' . (!array_key_exists('INTERVAL', $form_values) ? 1 : $form_values['INTERVAL']);

		// Unset the empty 'All' values.
		if (array_key_exists('BYDAY', $form_values)) unset($form_values['BYDAY']['']);
		if (array_key_exists('BYMONTH', $form_values)) unset($form_values['BYMONTH']['']);
		if (array_key_exists('BYMONTHDAY', $form_values)) unset($form_values['BYMONTHDAY']['']);

		if (array_key_exists('BYDAY', $form_values) && $BYDAY = implode(",", $form_values['BYDAY'])) {
			$RRULE .= ';BYDAY=' . $BYDAY;
		}
		if (array_key_exists('BYMONTH', $form_values) && $BYMONTH = implode(",", $form_values['BYMONTH'])) {
			$RRULE .= ';BYMONTH=' . $BYMONTH;
		}
		if (array_key_exists('BYMONTHDAY', $form_values) && $BYMONTHDAY = implode(",", $form_values['BYMONTHDAY'])) {
			$RRULE .= ';BYMONTHDAY=' . $BYMONTHDAY;
		}
		// The UNTIL date is supposed to always be expressed in UTC.
		// The input date values may already have been converted to a date
		// object on a previous pass, so check for that.
		if (array_key_exists('UNTIL', $form_values) && array_key_exists('datetime', $form_values['UNTIL']) && !empty($form_values['UNTIL']['datetime'])) {
			$until = !is_object($form_values['UNTIL']['datetime']) ? date_ical_date($form_values['UNTIL'], 'UTC') : $form_values['UNTIL']['datetime'];
			$RRULE .= ';UNTIL=' . date_format($until, DATE_FORMAT_ICAL) . 'Z';
		}
		// Our form doesn't allow a value for COUNT, but it may be needed by
		// modules using the API, so add it to the rule.
		if (array_key_exists('COUNT', $form_values)) {
			$RRULE .= ';COUNT=' . $form_values['COUNT'];
		}

		// iCal rules presume the week starts on Monday unless otherwise specified,
		// so we'll specify it.
		if (array_key_exists('WKST', $form_values)) {
			$RRULE .= ';WKST=' . $form_values['WKST'];
		}
		else {
			$RRULE .= ';WKST=' . self::date_repeat_dow2day(1);
		}

		// Exceptions dates go last, on their own line.
		// The input date values may already have been converted to a date
		// object on a previous pass, so check for that.
		if (isset($form_values['EXDATE']) && is_array($form_values['EXDATE'])) {
			$ex_dates = array();
			foreach ($form_values['EXDATE'] as $value) {
				if (!empty($value['datetime'])) {
					$date = !is_object($value['datetime']) ? self::date_ical_date($value, 'UTC') : $value['datetime'];
					$ex_date = !empty($date) ? $date->format(DATE_FORMAT_ICAL) : '';
					if (!empty($ex_date)) {
						$ex_dates[] = $ex_date;
					}
				}
			}
			if (!empty($ex_dates)) {
				sort($ex_dates);
				$RRULE .= chr(13) . chr(10) . 'EXDATE:' . implode(',', $ex_dates);
			}
		}
		elseif (!empty($form_values['EXDATE'])) {
			$RRULE .= chr(13) . chr(10) . 'EXDATE:' . $form_values['EXDATE'];
		}

		// Exceptions dates go last, on their own line.
		if (isset($form_values['RDATE']) && is_array($form_values['RDATE'])) {
			$ex_dates = array();
			foreach ($form_values['RDATE'] as $value) {
				$date = !is_object($value['datetime']) ? self::date_ical_date($value, 'UTC') : $value['datetime'];
				$ex_date = !empty($date) ? $date->format(DATE_FORMAT_ICAL) : '';
				if (!empty($ex_date)) {
					$ex_dates[] = $ex_date;
				}
			}
			if (!empty($ex_dates)) {
				sort($ex_dates);
				$RRULE .= chr(13) . chr(10) .'RDATE:'. implode(',', $ex_dates);
			}
		}
		elseif (!empty($form_values['RDATE'])) {
			$RRULE .= chr(13) . chr(10) .'RDATE:'. $form_values['RDATE'];
		}

		return $RRULE;
	}

	/**
	 * Code to compute the dates that match an iCal RRULE.
	 *
	 * Moved to a separate file since it is not used on most pages
	 * so the code is not parsed unless needed.
	 *
	 * Extensive simpletests have been created to test the RRULE calculation
	 * results against official examples from RFC 2445.
	 *
	 * These calculations are expensive and results should be stored or cached
	 * so the calculation code is not called more often than necessary.
	 *
	 * Currently implemented:
	 * INTERVAL, UNTIL, COUNT, EXDATE, RDATE, BYDAY, BYMONTHDAY, BYMONTH,
	 * YEARLY, MONTHLY, WEEKLY, DAILY
	 *
	 * Currently not implemented:
	 *
	 * BYYEARDAY, MINUTELY, HOURLY, SECONDLY, BYMINUTE, BYHOUR, BYSECOND
	 *   These could be implemented in the future.
	 *
	 * BYSETPOS
	 *   Seldom used anywhere, so no reason to complicated the code.
	 */

	/**
	 * Private implementation of date_repeat_calc().
	 *
	 * Compute dates that match the requested rule, within a specified date range.
	 */
	public static function date_repeat_calc($rrule, $start, $end, $exceptions = array(), $timezone = NULL, $additions = array()) {

		if (empty($timezone)) {
			$timezone = Yii::app()->getTimeZone();
		}

		// Make sure the 'EXCEPTIONS' string isn't appended to the rule.
		$parts = explode("\n", $rrule);
		if (count($parts)) {
			$rrule = $parts[0];
		}
		// Get the parsed array of rule values.
		$rrule = self::date_ical_parse_rrule('RRULE:', $rrule);

		// Create a date object for the start and end dates.
		$start_date = new DateObject($start, $timezone);
		$end_date = new DateObject($end, $timezone);

		// If the rule has an UNTIL, see if that is earlier than the end date.
		if (!empty($rrule['UNTIL'])) {
			$until_date = self::date_ical_date($rrule['UNTIL'], $timezone);
			if (date_format($until_date, 'U') < date_format($end_date, 'U')) {
				$end_date = $until_date;
			}
		}

		// Get an integer value for the interval, if none given, '1' is implied.
		$interval = max(1, isset($rrule['INTERVAL']) ? $rrule['INTERVAL'] : 1);
		$count = isset($rrule['COUNT']) ? $rrule['COUNT'] : NULL;

		if (empty($rrule['FREQ'])) {
			$rrule['FREQ'] = 'DAILY';
		}

		// Make sure DAILY frequency isn't used in places it won't work;
		if (!empty($rrule['BYMONTHDAY']) && !in_array($rrule['FREQ'], array('MONTHLY', 'YEARLY'))) {
			$rrule['FREQ'] = 'MONTHLY';
		}
		else if (!empty($rrule['BYDAY']) && !in_array($rrule['FREQ'], array('MONTHLY', 'WEEKLY', 'YEARLY'))) {
			$rrule['FREQ'] = 'WEEKLY';
		}

		// Find the time period to jump forward between dates.
		switch ($rrule['FREQ']) {
			case 'DAILY':
				$jump = $interval . ' days';
				break;
			case 'WEEKLY':
				$jump = $interval . ' weeks';
				break;
			case 'MONTHLY':
				$jump = $interval . ' months';
				break;
			case 'YEARLY':
				$jump = $interval . ' years';
				break;
		}
		$rrule = self::date_repeat_adjust_rrule($rrule, $start_date);

		// The start date always goes into the results, whether or not it meets
		// the rules. RFC 2445 includes examples where the start date DOES NOT
		// meet the rules, but the expected results always include the start date.
		$days = array(date_format($start_date, DATE_FORMAT_DATETIME));

		// BYMONTHDAY will look for specific days of the month in one or more months.
		// This process is only valid when frequency is monthly or yearly.

		if (!empty($rrule['BYMONTHDAY'])) {
			$finished = FALSE;
			$current_day = clone($start_date);
			$direction_days = array();
			// Deconstruct the day in case it has a negative modifier.
			foreach ($rrule['BYMONTHDAY'] as $day) {
				preg_match("@(-)?([0-9]{1,2})@", $day, $regs);
				if (!empty($regs[2])) {
					// Convert parameters into full day name, count, and direction.
					$direction_days[$day] = array(
          'direction' => !empty($regs[1]) ? $regs[1] : '+',
          'direction_count' => $regs[2],
					);
				}
			}
			while (!$finished) {
				$period_finished = FALSE;
				while (!$period_finished) {
					foreach ($rrule['BYMONTHDAY'] as $monthday) {
						$day = $direction_days[$monthday];
						$current_day = self::date_repeat_set_month_day($current_day, NULL, $day['direction_count'], $day['direction'], $timezone);
						self::date_repeat_add_dates($days, $current_day, $start_date, $end_date, $exceptions, $rrule);
						if ($finished = self::date_repeat_is_finished($current_day, $days, $count, $end_date)) {
							$period_finished = TRUE;
						}
					}
					// If it's monthly, keep looping through months, one INTERVAL at a time.
					if ($rrule['FREQ'] == 'MONTHLY') {
						if ($finished = self::date_repeat_is_finished($current_day, $days, $count, $end_date)) {
							$period_finished = TRUE;
						}
						// Back up to first of month and jump.
						$current_day = self::date_repeat_set_month_day($current_day, NULL, 1, '+', $timezone);
						date_modify($current_day, '+'. $jump);
					}
					// If it's yearly, break out of the loop at the
					// end of every year, and jump one INTERVAL in years.
					else {
						if (date_format($current_day, 'n') == 12) {
							$period_finished = TRUE;
						}
						else {
							// Back up to first of month and jump.
							$current_day = self::date_repeat_set_month_day($current_day, NULL, 1, '+', $timezone);
							date_modify($current_day, '+1 month');
						}
					}
				}
				if ($rrule['FREQ'] == 'YEARLY') {
					// Back up to first of year and jump.
					$current_day = self::date_repeat_set_year_day($current_day, NULL, 1, '+', $timezone);
					date_modify($current_day, '+'. $jump);
				}
				$finished = self::date_repeat_is_finished($current_day, $days, $count, $end_date);
			}
		}

		// This is the simple fallback case, not looking for any BYDAY,
		// just repeating the start date. Because of imputed BYDAY above, this
		// will only test TRUE for a DAILY or less frequency (like HOURLY).

		elseif (empty($rrule['BYDAY'])) {
			// $current_day will keep track of where we are in the calculation.
			$current_day = clone($start_date);
			$finished = FALSE;
			$months = !empty($rrule['BYMONTH']) ? $rrule['BYMONTH'] : array();
			while (!$finished) {
				self::date_repeat_add_dates($days, $current_day, $start_date, $end_date, $exceptions, $rrule);
				$finished = self::date_repeat_is_finished($current_day, $days, $count, $end_date);
				date_modify($current_day, '+'. $jump);
			}
		}

		else {

			// More complex searches for day names and criteria like '-1SU' or '2TU,2TH',
			// require that we interate through the whole time period checking each BYDAY.

			// Create helper array to pull day names out of iCal day strings.
			$day_names = self::date_repeat_dow_day_options(FALSE);
			$days_of_week = array_keys($day_names);

			// Parse out information about the BYDAYs and separate them
			// depending on whether they have directional parameters like -1SU or 2TH.
			$month_days = array();
			$week_days = array();

			// Find the right first day of the week to use, iCal rules say Monday
			// should be used if none is specified.
			$week_start_rule = !empty($rrule['WKST']) ? trim($rrule['WKST']) : 'MO';
			$week_start_day = $day_names[$week_start_rule];

			// Make sure the week days array is sorted into week order,
			// we use the $ordered_keys to get the right values into the key
			// and force the array to that order. Needed later when we
			// iterate through each week looking for days so we don't
			// jump to the next week when we hit a day out of order.
			$ordered = self::date_repeat_days_ordered($week_start_rule);
			$ordered_keys = array_flip($ordered);

			foreach ($rrule['BYDAY'] as $day) {
				preg_match("@(-)?([0-9]+)?([SU|MO|TU|WE|TH|FR|SA]{2})@", trim($day), $regs);
				if (!empty($regs[2])) {
					// Convert parameters into full day name, count, and direction.
					$direction_days[] = array(
          'day' => $day_names[$regs[3]],
          'direction' => !empty($regs[1]) ? $regs[1] : '+',
          'direction_count' => $regs[2],
					);
				}
				else {
					$week_days[$ordered_keys[$regs[3]]] = $day_names[$regs[3]];
				}
			}
			ksort($week_days);

			// BYDAYs with parameters like -1SU (last Sun) or 2TH (second Thur)
			// need to be processed one month or year at a time.
			if (!empty($direction_days) && in_array($rrule['FREQ'], array('MONTHLY', 'YEARLY'))) {
				$finished = FALSE;
				$current_day = clone($start_date);
				while (!$finished) {
					foreach ($direction_days as $day) {
						// Find the BYDAY date in the current month.
						if ($rrule['FREQ'] == 'MONTHLY') {
							$current_day = self::date_repeat_set_month_day($current_day, $day['day'], $day['direction_count'], $day['direction'], $timezone);
						}
						else {
							$current_day = self::date_repeat_set_year_day($current_day, $day['day'], $day['direction_count'], $day['direction'], $timezone);
						}
						self::date_repeat_add_dates($days, $current_day, $start_date, $end_date, $exceptions, $rrule);
					}
					$finished = self::date_repeat_is_finished($current_day, $days, $count, $end_date);
					// Reset to beginning of period before jumping to next period.
					// Needed especially when working with values like 'last Saturday'
					// to be sure we don't skip months like February.
					$year = date_format($current_day, 'Y');
					$month = date_format($current_day, 'n');
					if ($rrule['FREQ'] == 'MONTHLY') {
						date_date_set($current_day, $year, $month, 1);
					}
					else {
						date_date_set($current_day, $year, 1, 1);
					}
					// Jump to the next period.
					date_modify($current_day, '+' . $jump);
				}
			}

			// For BYDAYs without parameters,like TU,TH (every Tues and Thur),
			// we look for every one of those days during the frequency period.
			// Iterate through periods of a WEEK, MONTH, or YEAR, checking for
			// the days of the week that match our criteria for each week in the
			// period, then jumping ahead to the next week, month, or year,
			// an INTERVAL at a time.

			if (!empty($week_days) && in_array($rrule['FREQ'], array('MONTHLY', 'WEEKLY', 'YEARLY'))) {
				$finished = FALSE;
				$current_day = clone($start_date);
				$format = $rrule['FREQ'] == 'YEARLY' ? 'Y' : 'n';
				$current_period = date_format($current_day, $format);

				// Back up to the beginning of the week in case we are somewhere in the
				// middle of the possible week days, needed so we don't prematurely
				// jump to the next week. Theself::date_repeat_add_dates() function will
				// keep dates outside the range from getting added.
				if (date_format($current_day, 'l') != $day_names[$day]) {
					date_modify($current_day, '-1 ' . $week_start_day);
				}
				while (!$finished) {
					$period_finished = FALSE;
					while (!$period_finished) {
						$moved = FALSE;
						foreach ($week_days as $delta => $day) {
							// Find the next occurence of each day in this week, only add it
							// if we are still in the current month or year. Theself::date_repeat_add_dates
							// function is insufficient to test whether to include this date
							// if we are using a rule like 'every other month', so we must
							// explicitly test it here.

							// If we're already on the right day, don't jump or we
							// will prematurely move into the next week.
							if (date_format($current_day, 'l') != $day) {
								date_modify($current_day, '+1 ' . $day);
								$moved = TRUE;
							}
							if ($rrule['FREQ'] == 'WEEKLY' || date_format($current_day, $format) == $current_period) {
								self::date_repeat_add_dates($days, $current_day, $start_date, $end_date, $exceptions, $rrule);
							}
						}
						$finished = self::date_repeat_is_finished($current_day, $days, $count, $end_date);

						// Make sure we don't get stuck in endless loop if the current
						// day never got changed above.
						if (!$moved) {
							date_modify($current_day, '+1 day');
						}

						// If this is a WEEKLY frequency, stop after each week,
						// otherwise, stop when we've moved outside the current period.
						// Jump to the end of the week, then test the period.
						if ($finished || $rrule['FREQ'] == 'WEEKLY') {
							$period_finished = TRUE;
						}
						elseif ($rrule['FREQ'] != 'WEEKLY' && date_format($current_day, $format) != $current_period) {
							$period_finished = TRUE;
						}
					}

					if ($finished) {
						continue;
					}

					// We'll be at the end of a week, month, or year when
					// we get to this point in the code.

					// Go back to the beginning of this period before we jump, to
					// ensure we jump to the first day of the next period.
					switch ($rrule['FREQ']) {
						case 'WEEKLY':
							date_modify($current_day, '+1 ' . $week_start_day);
							date_modify($current_day, '-1 week');
							break;
						case 'MONTHLY':
							date_modify($current_day, '-' . (date_format($current_day, 'j') - 1) . ' days');
							date_modify($current_day, '-1 month');
							break;
						case 'YEARLY':
							date_modify($current_day, '-' . date_format($current_day, 'z') . ' days');
							date_modify($current_day, '-1 year');
							break;
					}
					// Jump ahead to the next period to be evaluated.
					date_modify($current_day, '+' . $jump);
					$current_period = date_format($current_day, $format);
					$finished = self::date_repeat_is_finished($current_day, $days, $count, $end_date);
				}
			}
		}

		// add additional dates
		foreach($additions as $addition) {
			$days[] = date_format($addition, DATE_FORMAT_DATETIME);
		}

		sort($days);
		return $days;
	}

	/**
	 * See if the RRULE needs some imputed values added to it.
	 */
	public static function date_repeat_adjust_rrule($rrule, $start_date) {
		// If this is not a valid value, do nothing;
		if (empty($rrule) || empty($rrule['FREQ'])) {
			return array();
		}

		// RFC 2445 says if no day or monthday is specified when creating repeats for
		// weeks, months, or years, impute the value from the start date.

		if (empty($rrule['BYDAY']) && $rrule['FREQ'] == 'WEEKLY') {
			$rrule['BYDAY'] = array(self::date_repeat_dow2day(date_format($start_date, 'w')));
		}
		elseif (empty($rrule['BYDAY']) && empty($rrule['BYMONTHDAY']) && $rrule['FREQ'] == 'MONTHLY') {
			$rrule['BYMONTHDAY'] = array(date_format($start_date, 'j'));
		}
		elseif (empty($rrule['BYDAY']) && empty($rrule['BYMONTHDAY']) && empty($rrule['BYYEARDAY']) && $rrule['FREQ'] == 'YEARLY') {
			$rrule['BYMONTHDAY'] = array(date_format($start_date, 'j'));
			if (empty($rrule['BYMONTH'])) {
				$rrule['BYMONTH'] = array(date_format($start_date, 'n'));
			}
		}
		// If we are processing rules for period other than YEARLY or MONTHLY
		// and have BYDAYS like 2SU or -1SA, simplify them to SU or SA since the
		// position rules make no sense in other periods and just add complexity.

		elseif (!empty($rrule['BYDAY']) && !in_array($rrule['FREQ'], array('MONTHLY', 'YEARLY'))) {
			foreach ($rrule['BYDAY'] as $delta => $BYDAY) {
				$rrule['BYDAY'][$delta] = substr($BYDAY, -2);
			}
		}

		return $rrule;
	}

	/**
	 * Helper function to add found date to the $dates array.
	 *
	 * Check that the date to be added is between the start and end date
	 * and that it is not in the $exceptions, nor already in the $days array,
	 * and that it meets other criteria in the RRULE.
	 */
	public static function date_repeat_add_dates(&$days, $current_day, $start_date, $end_date, $exceptions, $rrule) {
		if (isset($rrule['COUNT']) && sizeof($days) >= $rrule['COUNT']) {
			return FALSE;
		}
		$formatted = date_format($current_day, DATE_FORMAT_DATETIME);
		if ($formatted > date_format($end_date, DATE_FORMAT_DATETIME)) {
			return FALSE;
		}
		if ($formatted < date_format($start_date, DATE_FORMAT_DATETIME)) {
			return FALSE;
		}
		if (in_array(date_format($current_day, 'Y-m-d'), $exceptions)) {
			return FALSE;
		}
		if (!empty($rrule['BYDAY'])) {
			$BYDAYS = $rrule['BYDAY'];
			foreach ($BYDAYS as $delta => $BYDAY) {
				$BYDAYS[$delta] = substr($BYDAY, -2);
			}
			if (!in_array(self::date_repeat_dow2day(date_format($current_day, 'w')), $BYDAYS)) {
				return FALSE;
			}}
			if (!empty($rrule['BYYEAR']) && !in_array(date_format($current_day, 'Y'), $rrule['BYYEAR'])) {
				return FALSE;
			}
			if (!empty($rrule['BYMONTH']) && !in_array(date_format($current_day, 'n'), $rrule['BYMONTH'])) {
				return FALSE;
			}
			if (!empty($rrule['BYMONTHDAY'])) {
				// Test month days, but only if there are no negative numbers.
				$test = TRUE;
				$BYMONTHDAYS = array();
				foreach ($rrule['BYMONTHDAY'] as $day) {
					if ($day > 0) {
						$BYMONTHDAYS[] = $day;
					}
					else {
						$test = FALSE;
						break;
					}
				}
				if ($test && !empty($BYMONTHDAYS) && !in_array(date_format($current_day, 'j'), $BYMONTHDAYS)) {
					return FALSE;
				}
			}
			// Don't add a day if it is already saved so we don't throw the count off.
			if (in_array($formatted, $days)) {
				return TRUE;
			}
			else {
				$days[] = $formatted;
			}
	}

	/**
	 * Stop when $current_day is greater than $end_date or $count is reached.
	 */
	public static function date_repeat_is_finished($current_day, $days, $count, $end_date) {
		if (($count && sizeof($days) >= $count)
		|| date_format($current_day, 'U') > date_format($end_date, 'U')) {
			return TRUE;
		}
		else {
			return FALSE;
		}
	}

	/**
	 * Set a date object to a specific day of the month.
	 *
	 * Example,
	 *   date_set_month_day($date, 'Sunday', 2, '-')
	 *   will reset $date to the second to last Sunday in the month.
	 *   If $day is empty, will set to the number of days from the
	 *   beginning or end of the month.
	 */
	public static function date_repeat_set_month_day($date_in, $day, $count = 1, $direction = '+', $timezone = 'UTC') {
		if (is_object($date_in)) {
			$current_month = date_format($date_in, 'n');

			// Reset to the start of the month.
			// We should be able to do this with date_date_set(), but
			// for some reason the date occasionally gets confused if run
			// through this function multiple times. It seems to work
			// reliably if we create a new object each time.
			$datetime = date_format($date_in, DATE_FORMAT_DATETIME);
			$datetime = substr_replace($datetime, '01', 8, 2);
			$date = new DateObject($datetime, $timezone);
			if ($direction == '-') {
				// For negative search, start from the end of the month.
				date_modify($date, '+1 month');
			}
			else {
				// For positive search, back up one day to get outside the
				// current month, so we can catch the first of the month.
				date_modify($date, '-1 day');
			}

			if (empty($day)) {
				date_modify($date, $direction . $count . ' days');
			}
			else {
				// Use the English text for order, like First Sunday
				// instead of +1 Sunday to overcome PHP5 bug, (see #369020).
				$order = self::date_order();
				$step = $count <= 5 ? $order[$direction . $count] : $count;
				date_modify($date, $step . ' ' . $day);
			}

			// If that takes us outside the current month, don't go there.
			if (date_format($date, 'n') == $current_month) {
				return $date;
			}
		}
		return $date_in;
	}

	/**
	 * Set a date object to a specific day of the year.
	 *
	 * Example,
	 *   date_set_year_day($date, 'Sunday', 2, '-')
	 *   will reset $date to the second to last Sunday in the year.
	 *   If $day is empty, will set to the number of days from the
	 *   beginning or end of the year.
	 */
	public static function date_repeat_set_year_day($date_in, $day, $count = 1, $direction = '+', $timezone = 'UTC') {
		if (is_object($date_in)) {
			$current_year = date_format($date_in, 'Y');

			// Reset to the start of the month.
			// See note above.
			$datetime = date_format($date_in, DATE_FORMAT_DATETIME);
			$datetime = substr_replace($datetime, '01-01', 5, 5);
			$date = new DateObject($datetime, $timezone);
			if ($direction == '-') {
				// For negative search, start from the end of the year.
				date_modify($date, '+1 year');
			}
			else {
				// For positive search, back up one day to get outside the
				// current year, so we can catch the first of the year.
				date_modify($date, '-1 day');
			}
			if (empty($day)) {
				date_modify($date, $direction . $count . ' days');
			}
			else {
				// Use the English text for order, like First Sunday
				// instead of +1 Sunday to overcome PHP5 bug, (see #369020).
				$order = self::date_order();
				$step = $count <= 5 ? $order[$direction . $count] : $count;
				date_modify($date, $step . ' ' . $day);
			}

			// If that takes us outside the current year, don't go there.
			if (date_format($date, 'Y') == $current_year) {
				return $date;
			}
		}
		return $date_in;
	}

	private static function date_pad($value, $size = 2) {
		return sprintf("%0" . $size . "d", $value);
	}

	/**
	 * Helper function for FREQ options.
	 */
	public static function FREQ_options() {
		return array(
		    'NONE' => Yii::t('app', '-- Period'),
		    'DAILY' => Yii::t('app', 'Days'),
		    'WEEKLY' => Yii::t('app', 'Weeks'),
		    'MONTHLY' => Yii::t('app', 'Months'),
		    'YEARLY' => Yii::t('app', 'Years'),
		);
	}

	public static function INTERVAL_options() {
		$options = array(
			0 => Yii::t('app', '-- Frequency'),
			1 => Yii::t('app', 'Every'),
		);
		for ($i = 2; $i < 367; $i++) {
			$options[$i] = Yii::t('app', 'Every @number', array('@number' => $i));
		}
		return $options;
	}
	/**
	 * Helper function for BYDAY options in Date Repeat
	 * and for converting back and forth from '+1' to 'First' .
	 */
	private static function date_order_translated() {
		return array(
    '+1' => Yii::t('date_order', 'First'),
    '+2' => Yii::t('app', 'Second'),
    '+3' => Yii::t('app', 'Third'),
    '+4' => Yii::t('app', 'Fourth'),
    '+5' => Yii::t('app', 'Fifth'),
    '-1' => Yii::t('app', 'Last'),
    '-2' => Yii::t('app', 'Next to last'),
    '-3' => Yii::t('app', 'Third from last'),
    '-4' => Yii::t('app', 'Fourth from last'),
    '-5' => Yii::t('app', 'Fifth from last')
		);
	}

	private static function date_order() {
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

	/**
	 * Helper function for FREQ options.
	 *
	 * Translated and untranslated arrays of the iCal day of week names.
	 * We need the untranslated values for date_modify(), translated
	 * values when displayed to user.
	 */
	private static function date_repeat_dow_day_options($translated = TRUE) {
		$weekdays = array('SU' => 'Sunday', 'MO' => 'Monday', 'TU' => 'Tuesday', 'WE' => 'Wednesday', 'TH' => 'Thursday', 'FR' => 'Friday', 'SA' => 'Saturday');
		if ($translated) return array_combine(array_keys($weekdays), Yii::app()->getLocale()->getWeekDayNames());
		else return $weekdays;
	}

	private static function date_repeat_dow_day_options_ordered($week_start) {
		$unordered = self::date_repeat_dow_day_options(FALSE);
		if (variable_get('date_first_day', 1) > 0) {
			for ($i = 1; $i <= variable_get('date_first_day', 1); $i++) {
				$last = array_shift($weekdays);
				array_push($weekdays, $last);
			}
		}
		return $weekdays;
	}

	/**
	 * Helper function for BYDAY options.
	 */
	private static function date_repeat_dow_count_options() {
		return array('' => Yii::t('app', 'Every' )) + self::date_order_translated();
	}

	/**
	 * Helper function for BYDAY options.
	 *
	 * Creates options like -1SU and 2TU
	 */
	public static function date_repeat_dow_options() {
		$options = array();
		foreach (self::date_repeat_dow_count_options() as $count_key => $count_value) {
			foreach (self::date_repeat_dow_day_options() as $dow_key => $dow_value) {
				$options[$count_key . $dow_key] = $count_value . ' ' . $dow_value;
			}
		}
		return $options;
	}

	/**
	 * Translate a day of week position to the iCal day name.
	 *
	 * Used with date_format($date, 'w') or get_variable('date_first_day'),
	 * which return 0 for Sunday, 1 for Monday, etc.
	 *
	 * dow 2 becomes 'TU', dow 3 becomes 'WE', and so on.
	 */
	private static function date_repeat_dow2day($dow) {
		$days_of_week = array_keys(self::date_repeat_dow_day_options(FALSE));
		return $days_of_week[$dow];
	}

	/**
	 * Shift the array of iCal day names into the right order
	 * for a specific week start day.
	 */
	private static function date_repeat_days_ordered($week_start_day) {
		$days = array_flip(array_keys(self::date_repeat_dow_day_options(FALSE)));
		$start_position = $days[$week_start_day];
		$keys = array_flip($days);
		if ($start_position > 0) {
			for ($i = 1; $i <= $start_position; $i++) {
				$last = array_shift($keys);
				array_push($keys, $last);
			}
		}
		return $keys;
	}

	/**
	 * Build a description of an iCal rule.
	 *
	 * Constructs a human-readable description of the rule.
	 */
	function date_repeat_rrule_description($rrule, $format = 'D M d Y') {
		// Empty or invalid value.
		if (empty($rrule) || !strstr($rrule, 'RRULE')) {
			return;
		}

		// Make sure there will be an empty description for any unused parts.
		$description = array(
    '!interval' => '',
    '!byday' => '',
    '!bymonth' => '',
    '!count' => '',
    '!until' => '',
    '!except' => '',
    '!additional' => '',
    '!week_starts_on' => '',
		);
		$parts = self::date_repeat_split_rrule($rrule);
		$additions = $parts[2];
		$exceptions = $parts[1];
		$rrule = $parts[0];
		$interval = self::INTERVAL_options();
		switch ($rrule['FREQ']) {
			case 'WEEKLY':
				$description['!interval'] = format_plural($rrule['INTERVAL'], 'every week', 'every @count weeks') . ' ';
				break;
			case 'MONTHLY':
				$description['!interval'] = format_plural($rrule['INTERVAL'], 'every month', 'every @count months') . ' ';
				break;
			case 'YEARLY':
				$description['!interval'] = format_plural($rrule['INTERVAL'], 'every year', 'every @count years') . ' ';
				break;
			default:
				$description['!interval'] = format_plural($rrule['INTERVAL'], 'every day', 'every @count days') . ' ';
				break;
		}

		if (!empty($rrule['BYDAY'])) {
			$days = date_repeat_dow_day_options();
			$counts = date_repeat_dow_count_options();
			$results = array();
			foreach ($rrule['BYDAY'] as $byday) {
				$day = substr($byday, -2);
				$count = intval(str_replace(' ' . $day, '', $byday));
				if ($count = intval(str_replace(' ' . $day, '', $byday))) {
					$results[] = trim(t('!repeats_every_interval on the !date_order !day_of_week', array('!repeats_every_interval ' => '', '!date_order' => strtolower($counts[substr($byday, 0, 2)]), '!day_of_week' => $days[$day])));
				}
				else {
					$results[] = trim(t('!repeats_every_interval every !day_of_week', array('!repeats_every_interval ' => '', '!day_of_week' => $days[$day])));
				}
			}
			$description['!byday'] = implode(' ' . t('and') . ' ', $results);
		}
		if (!empty($rrule['BYMONTH'])) {
			if (sizeof($rrule['BYMONTH']) < 12) {
				$results = array();
				$months = Yii::app()->getLocale()->getMonthNames();
				foreach ($rrule['BYMONTH'] as $month) {
					$results[] = $months[$month];
				}
				if (!empty($rrule['BYMONTHDAY'])) {
					$description['!bymonth'] = trim(t('!repeats_every_interval on the !month_days of !month_names', array('!repeats_every_interval ' => '', '!month_days' => implode(', ', $rrule['BYMONTHDAY']), '!month_names' => implode(', ', $results))));
				}
				else {
					$description['!bymonth'] = trim(t('!repeats_every_interval on !month_names', array('!repeats_every_interval ' => '', '!month_names' => implode(', ', $results))));
				}
			}
		}
		if ($rrule['INTERVAL'] < 1) {
			$rrule['INTERVAL'] = 1;
		}
		if (!empty($rrule['COUNT'])) {
			$description['!count'] = trim(t('!repeats_every_interval !count times', array('!repeats_every_interval ' => '', '!count' => $rrule['COUNT'])));
		}
		if (!empty($rrule['UNTIL'])) {
			$until = date_ical_date($rrule['UNTIL'], 'UTC');
			date_timezone_set($until, date_default_timezone_object());
			$description['!until'] = trim(t('!repeats_every_interval until !until_date', array('!repeats_every_interval ' => '', '!until_date' => date_format_date($until, 'custom', $format))));
		}
		if ($exceptions) {
			$values = array();
			foreach ($exceptions as $exception) {
				$values[] = date_format_date(date_ical_date($exception), 'custom', $format);
			}
			$description['!except'] = trim(t('!repeats_every_interval except !except_dates', array('!repeats_every_interval ' => '', '!except_dates' => implode(', ', $values))));
		}
		if (!empty($rrule['WKST'])) {
			$day_names = date_repeat_dow_day_options();
			$description['!week_starts_on'] = trim(t('!repeats_every_interval where the week start on !day_of_week', array('!repeats_every_interval ' => '', '!day_of_week' => $day_names[trim($rrule['WKST'])])));
		}
		if ($additions) {
			$values = array();
			foreach ($additions as $addition) {
				$values[] = date_format_date(date_ical_date($addition), 'custom', $format);
			}
			$description['!additional'] = trim(t('Also includes !additional_dates.', array('!additional_dates' => implode(', ', $values))));
		}
		return t('Repeats !interval !bymonth !byday !count !until !except. !additional', $description);
	}

	/**
	 * Parse an iCal rule into a parsed RRULE array and an EXDATE array.
	 */
	public static function date_repeat_split_rrule($rrule) {
		$parts = explode("\n", $rrule);
		$rrule = array();
		$exceptions = array();
		$additions = array();
		$additions = array();
		foreach ($parts as $part) {
			if (strstr($part, 'RRULE')) {
				$RRULE = str_replace('RRULE:', '', $part);
				$rrule = (array) date_ical_parse_rrule('RRULE:', $RRULE);
			}
			elseif (strstr($part, 'EXDATE')) {
				$EXDATE = str_replace('EXDATE:', '', $part);
				$exceptions = (array) date_ical_parse_exceptions('EXDATE:', $EXDATE);
				unset($exceptions['DATA']);
			}
			elseif (strstr($part, 'RDATE')) {
				$RDATE = str_replace('RDATE:', '', $part);
				$additions = (array) date_ical_parse_exceptions('RDATE:', $RDATE);
				unset($additions['DATA']);
			}
		}
		return array($rrule, $exceptions, $additions);
	}

	private static function _date_timezone_replacement($old) {
  $replace = array (
  'Brazil/Acre' => 'America/Rio_Branco',
  'Brazil/DeNoronha' => 'America/Noronha',
  'Brazil/East' => 'America/Recife',
  'Brazil/West' => 'America/Manaus',
  'Canada/Atlantic' => 'America/Halifax',
  'Canada/Central' => 'America/Winnipeg',
  'Canada/East-Saskatchewan' => 'America/Regina',
  'Canada/Eastern' => 'America/Toronto',
  'Canada/Mountain' =>'America/Edmonton',
  'Canada/Newfoundland' => 'America/St_Johns',
  'Canada/Pacific' => 'America/Vancouver',
  'Canada/Saskatchewan' => 'America/Regina',
  'Canada/Yukon' => 'America/Whitehorse',
  'CET' => 'Europe/Berlin',
  'Chile/Continental' => 'America/Santiago',
  'Chile/EasterIsland' => 'Pacific/Easter',
  'CST6CDT' => 'America/Chicago',
  'Cuba' => 'America/Havana',
  'EET' => 'Europe/Bucharest',
  'Egypt' => 'Africa/Cairo',
  'Eire' => 'Europe/Belfast',
  'EST' => 'America/New_York',
  'EST5EDT' => 'America/New_York',
  'GB' => 'Europe/London',
  'GB-Eire' => 'Europe/Belfast',
  'Etc/GMT' => 'UTC',
  'Etc/GMT+0' => 'UTC',
  'Etc/GMT+1' => 'UTC',
  'Etc/GMT+10' => 'UTC',
  'Etc/GMT+11' => 'UTC',
  'Etc/GMT+12' => 'UTC',
  'Etc/GMT+2' => 'UTC',
  'Etc/GMT+3' => 'UTC',
  'Etc/GMT+4' => 'UTC',
  'Etc/GMT+5' => 'UTC',
  'Etc/GMT+6' => 'UTC',
  'Etc/GMT+7' => 'UTC',
  'Etc/GMT+8' => 'UTC',
  'Etc/GMT+9' => 'UTC',
  'Etc/GMT-0' => 'UTC',
  'Etc/GMT-1' => 'UTC',
  'Etc/GMT-10' => 'UTC',
  'Etc/GMT-11' => 'UTC',
  'Etc/GMT-12' => 'UTC',
  'Etc/GMT-13' => 'UTC',
  'Etc/GMT-14' => 'UTC',
  'Etc/GMT-2' => 'UTC',
  'Etc/GMT-3' => 'UTC',
  'Etc/GMT-4' => 'UTC',
  'Etc/GMT-5' => 'UTC',
  'Etc/GMT-6' => 'UTC',
  'Etc/GMT-7' => 'UTC',
  'Etc/GMT-8' => 'UTC',
  'Etc/GMT-9' => 'UTC',
  'Etc/GMT0' => 'UTC',
  'Etc/Greenwich' => 'UTC',
  'Etc/UCT' => 'UTC',
  'Etc/Universal' => 'UTC',
  'Etc/UTC' => 'UTC',
  'Etc/Zulu' => 'UTC',
  'Factory' => 'UTC',
  'GMT' => 'UTC',
  'GMT+0' => 'UTC',
  'GMT-0' => 'UTC',
  'GMT0' => 'UTC',
  'Hongkong' => 'Asia/Hong_Kong',
  'HST' => 'Pacific/Honolulu',
  'Iceland' => 'Atlantic/Reykjavik',
  'Iran' => 'Asia/Tehran',
  'Israel' => 'Asia/Tel_Aviv',
  'Jamaica' => 'America/Jamaica',
  'Japan' => 'Asia/Tokyo',
  'Kwajalein' => 'Pacific/Kwajalein',
  'Libya' => 'Africa/Tunis',
  'MET' => 'Europe/Budapest',
  'Mexico/BajaNorte' => 'America/Tijuana',
  'Mexico/BajaSur' => 'America/Mazatlan',
  'Mexico/General' => 'America/Mexico_City',
  'MST' => 'America/Boise',
  'MST7MDT' => 'America/Boise',
  'Navajo' => 'America/Phoenix',
  'NZ' => 'Pacific/Auckland',
  'NZ-CHAT' => 'Pacific/Chatham',
  'Poland' => 'Europe/Warsaw',
  'Portugal' => 'Europe/Lisbon',
  'PRC' => 'Asia/Chongqing',
  'PST8PDT' => 'America/Los_Angeles',
  'ROC' => 'Asia/Taipei',
  'ROK' => 'Asia/Seoul',
  'Singapore' =>'Asia/Singapore',
  'Turkey' => 'Europe/Istanbul',
  'US/Alaska' => 'America/Anchorage',
  'US/Aleutian' => 'America/Adak',
  'US/Arizona' => 'America/Phoenix',
  'US/Central' => 'America/Chicago',
  'US/East-Indiana' => 'America/Indianapolis',
  'US/Eastern' => 'America/New_York',
  'US/Hawaii' => 'Pacific/Honolulu',
  'US/Indiana-Starke' => 'America/Indiana/Knox',
  'US/Michigan' => 'America/Detroit',
  'US/Mountain' => 'America/Boise',
  'US/Pacific' => 'America/Los_Angeles',
  'US/Pacific-New' => 'America/Los_Angeles',
  'US/Samoa' => 'Pacific/Samoa',
  'W-SU' => 'Europe/Moscow',
  'WET' => 'Europe/Paris',
  );
  if (array_key_exists($old, $replace)) {
    return $replace[$old];
  }
  else {
    return $old;
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
public static function date_timezone_names($required = FALSE, $refresh = FALSE) {
  static $zonenames;
  if (empty($zonenames) || $refresh) {
    $cached = Yii::app()->getCache()->get('date_timezone_identifiers_list');
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
        Yii::app()->getCache()->set('date_timezone_identifiers_list', $zonenames);
      }
    }
    foreach ($zonenames as $zone) {
      $zonenames[$zone] = Yii::t('app', '!timezone', array('!timezone' => $zone));
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
public static function date_timezone_abbr($refresh = FALSE) {
  $cached = Yii::app()->getCache()->get('date_timezone_abbreviations');
  $data = isset($cached->data) ? $cached->data : array();
  if (empty($data) || $refresh) {
    $data = array_keys(timezone_abbreviations_list());
    Yii::app()->getCache()->set('date_timezone_abbreviations', $data);
  }
  return $data;
}

/**
 * A date object for the current time.
 *
 * @param $timezone
 *   Optional method to force time to a specific timezone,
 *   defaults to user timezone, if set, otherwise site timezone.
 * @return object date
 */
public static function date_now($timezone = NULL) {
  return new DateObject('now', $timezone);
}

public static function date_timezone_is_valid($timezone) {
  static $timezone_names;
  if (empty($timezone_names)) {
    $timezone_names = array_keys(self::date_timezone_names(TRUE));
  }
  if (!in_array($timezone, $timezone_names)) {
    return FALSE;
  }
  return TRUE;
}

}
