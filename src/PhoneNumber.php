<?php namespace Propaganistas\LaravelPhone;

use Exception;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use JsonSerializable;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Propaganistas\LaravelPhone\Exceptions\PhoneCountryException;
use Propaganistas\LaravelPhone\Exceptions\PhoneFormatException;
use Propaganistas\LaravelPhone\Traits\ParsesCountries;
use Propaganistas\LaravelPhone\Traits\ParsesFormats;
use Propaganistas\LaravelPhone\Traits\ParsesTypes;
use Serializable;

class PhoneNumber implements Jsonable, JsonSerializable, Serializable
{
    use ParsesCountries,
        ParsesFormats,
        ParsesTypes;

    /**
     * The provided phone number.
     *
     * @var string
     */
    protected $number;

    /**
     * The provided phone countries.
     *
     * @var array
     */
    protected $countries = [];

    /**
     * @var \libphonenumber\PhoneNumber
     */
    protected $instance;

    /**
     * @var \libphonenumber\PhoneNumberUtil
     */
    protected $lib;

    /**
     * Phone constructor.
     *
     * @param string $number
     */
    public function __construct($number)
    {
        $this->number = $number;
        $this->lib = PhoneNumberUtil::getInstance();
    }

    /**
     * Create a phone instance.
     *
     * @param string       $number
     * @param string|array $country
     * @return static
     */
    public static function make($number, $country = null)
    {
        $instance = new static($number);

        return $instance->ofCountry($country);
    }

    /**
     * Set the country to which the phone number belongs to.
     *
     * @param string|array $country
     * @return $this
     */
    public function ofCountry($country)
    {
        $instance = clone $this;
        $instance->countries = $instance->parseCountries($country);

        return $instance;
    }

    /**
     * Format the phone number in international format.
     *
     * @return string
     */
    public function formatInternational()
    {
        return $this->format(PhoneNumberFormat::INTERNATIONAL);
    }

    /**
     * Format the phone number in national format.
     *
     * @return string
     */
    public function formatNational()
    {
        return $this->format(PhoneNumberFormat::NATIONAL);
    }

    /**
     * Format the phone number in E164 format.
     *
     * @return string
     */
    public function formatE164()
    {
        return $this->format(PhoneNumberFormat::E164);
    }

    /**
     * Format the phone number in RFC3966 format.
     *
     * @return string
     */
    public function formatRFC3966()
    {
        return $this->format(PhoneNumberFormat::RFC3966);
    }

    /**
     * Format the phone number in a given format.
     *
     * @param string $format
     * @return string
     */
    public function format($format)
    {
        $format = static::parseFormat($format);

        if (is_null($format)) {
            return $this->throwFormatException('Unknown format "' . (string) $format . '"');
        }

        $country = Arr::get($this->countries, 0);

        if (! $country && ! Str::startsWith($this->number, '+')) {
            return $this->throwFormatException('A country should be provided or the number should be in international format');
        }

        return $this->lib->format(
            $this->getPhoneNumberInstance(),
            $format
        );
    }

    /**
     * Format the phone number in a way that it can be dialled from the provided country.
     *
     * @param string $country
     * @return string
     */
    public function formatForCountry($country)
    {
        if (! static::isValidCountryCode($country)) {
            return $this->throwCountryException($country);
        }

        return $this->lib->formatOutOfCountryCallingNumber(
            $this->getPhoneNumberInstance(),
            $country
        );
    }

    /**
     * Format the phone number in a way that it can be dialled from the provided country using a cellphone.
     *
     * @param string $country
     * @param bool   $removeFormatting
     * @return string
     */
    public function formatForMobileDialingInCountry($country, $removeFormatting = false)
    {
        if (! static::isValidCountryCode($country)) {
            return $this->throwCountryException($country);
        }

        return $this->lib->formatNumberForMobileDialing(
            $this->getPhoneNumberInstance(),
            $country,
            $removeFormatting
        );
    }

    /**
     * Get the phone number's country.
     *
     * @return string
     */
    public function getCountry()
    {
        return $this->lib->getRegionCodeForNumber($this->getPhoneNumberInstance());
    }

    /**
     * Check if the phone number is of (a) given country(/countries).
     *
     * @param string $country
     * @return bool
     */
    public function isOfCountry($country)
    {
        $countries = static::parseCountries($country);

        return in_array($this->getCountry(), $countries, true);
    }

    /**
     * Get the phone number's type.
     *
     * @param bool $asConstant
     * @return string|int|null
     */
    public function getType($asConstant = false)
    {
        $type = $this->lib->getNumberType($this->getPhoneNumberInstance());

        return $asConstant ? $type : Arr::first(static::parseTypesAsStrings($type)) ?: null;
    }

    /**
     * Check if the phone number is of (a) given type(s).
     *
     * @param string $type
     * @return bool
     */
    public function isOfType($type)
    {
        $types = static::parseTypes($type);

        return in_array($this->getType(true), $types, true);
    }

    /**
     * Get the PhoneNumber instance of the current number.
     *
     * @return \libphonenumber\PhoneNumber
     */
    public function getPhoneNumberInstance()
    {
        // Got an instance cached?
        if ($this->instance) {
            return $this->instance;
        }

        // Let's try each provided country.
        foreach ($this->countries as $country) {
            try {
                return $this->instance = $this->lib->parse($this->number, $country);
            } catch (NumberParseException $exception) {
                // Continue the loop.
            }
        }

        // Otherwise let's try to autodetect the country if the number is in international format.
        if (Str::startsWith($this->number, '+')) {
            try {
                return $this->instance = $this->lib->parse($this->number, null);
            } catch (NumberParseException $exception) {
                // Proceed to throwing a custom exception.
            }
        }

        return $this->throwCountryException($this->number);
    }

    /**
     * Throw a IndeterminablePhoneCountryException.
     *
     * @param $message
     * @throws \Propaganistas\LaravelPhone\Exceptions\PhoneCountryException
     */
    protected function throwCountryException($message)
    {
        throw new PhoneCountryException($message);
    }

    /**
     * Throw a PhoneFormatException.
     *
     * @param string $message
     * @throws \Propaganistas\LaravelPhone\Exceptions\PhoneFormatException
     */
    protected function throwFormatException($message)
    {
        throw new PhoneFormatException($message);
    }

    /**
     * Convert the phone instance to JSON.
     *
     * @param  int $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convert the phone instance into something JSON serializable.
     *
     * @return string
     */
    public function jsonSerialize()
    {
        return $this->formatE164();
    }

    /**
     * Convert the phone instance into a string representation.
     *
     * @return string
     */
    public function serialize()
    {
        return $this->formatE164();
    }

    /**
     * Reconstructs the phone instance from a string representation.
     *
     * @param string $serialized
     */
    public function unserialize($serialized)
    {
        $this->number = $serialized;
        $this->countries = [];
    }

    /**
     * Convert the phone instance to a formatted number.
     *
     * @return string
     */
    public function __toString()
    {
        // Formatting the phone number could throw an exception, but __toString() doesn't cope well with that.
        // Let's just return the original number in that case and create a log entry...
        try {
            return $this->formatE164();
        } catch (Exception $exception) {
            return $this->number;
        }
    }
}