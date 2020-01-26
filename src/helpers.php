<?php

// Global functions with oxygen package

if (!function_exists('has_feature'))
{
	/**
	 *
	 * Check if a given feature is enabled in the application
	 * To edit features, see `config/features.php`
	 *
	 * @param $featureSlug
	 *
	 * @return \Illuminate\Config\Repository|mixed
	 */
	function has_feature($featureSlug)
	{
		// if the string doesn't start with `features.`, append it
		if (strpos($featureSlug, 'features.') !== 0) {
			$featureSlug = 'features.' . $featureSlug;
		}

		return (config($featureSlug, false));
	}
}

if (!function_exists('standard_datetime')) {
	/**
	 *
	 * Convert a date to EM's standard date time format
	 *
	 * @param null $dateTime
	 *
	 * @return null|string
	 */
	function standard_datetime($dateTime = null)
	{
		if (empty($dateTime)) return null;

		if ($dateTime instanceof \Carbon\Carbon) {
			return $dateTime->format(config('oxygen.dateTimeFormat'));
		}
	}
}

if (!function_exists('standard_date')) {
	/**
	 *
	 * Convert a date to EM's standard date format
	 *
	 * @param null $dateTime
	 *
	 * @return null|string
	 */
	function standard_date($dateTime = null)
	{
		if (empty($dateTime)) return null;

		if ($dateTime instanceof \Carbon\Carbon) {
			return $dateTime->format(config('oxygen.dateFormat'));
		}
	}
}

if (!function_exists('standard_time')) {
	/**
	 *
	 * Convert a date to EM's standard time format
	 *
	 * @param null $dateTime
	 *
	 * @return null|string
	 */
	function standard_time($dateTime = null)
	{
		if (empty($dateTime)) return null;

		if ($dateTime instanceof \Carbon\Carbon) {
			return $dateTime->format(config('oxygen.timeFormat'));
		}
	}
}