<?php namespace Propaganistas\LaravelPhone\Rules;

use Illuminate\Support\Arr;
use libphonenumber\PhoneNumberType;
use Propaganistas\LaravelPhone\Traits\ParsesCountries;
use Propaganistas\LaravelPhone\Traits\ParsesTypes;

class Phone
{
    use ParsesCountries,
        ParsesTypes;

    /**
     * The provided phone countries.
     *
     * @var array
     */
    protected $countries = [];

    /**
     * The provided phone types.
     *
     * @var array
     */
    protected $types = [];

    /**
     * Whether the number's country should be auto-detected.
     *
     * @var bool
     */
    protected $detect = false;

    /**
     * Whether to allow lenient checks (i.e. landline numbers without area codes).
     *
     * @var bool
     */
    protected $lenient = false;

    /**
     * Set the phone countries.
     *
     * @param string|array $country
     * @return $this
     */
    public function country($country)
    {
        $countries = is_array($country) ? $country : func_get_args();

        $this->countries = array_merge(
            $this->countries,
            static::parseCountries($countries)
        );

        return $this;
    }

    /**
     * Set the phone types.
     *
     * @param string|array $type
     * @return $this
     */
    public function type($type)
    {
        $types = is_array($type) ? $type : func_get_args();

        $this->types = array_merge(
            $this->types,
            static::parseTypesAsStrings($types)
        );

        return $this;
    }

    /**
     * Shortcut method for mobile type restriction.
     *
     * @return $this
     */
    public function mobile()
    {
        $this->type(PhoneNumberType::MOBILE);

        return $this;
    }

    /**
     * Shortcut method for fixed line type restriction.
     *
     * @return $this
     */
    public function fixedLine()
    {
        $this->type(PhoneNumberType::FIXED_LINE);

        return $this;
    }

    /**
     * Enable automatic country detection.
     *
     * @return $this
     */
    public function detect()
    {
        $this->detect = true;

        return $this;
    }

    /**
     * Enable lenient number checking.
     *
     * @return $this
     */
    public function lenient()
    {
        $this->lenient = true;

        return $this;
    }

    /**
     * Convert the rule to a validation string.
     *
     * @return string
     */
    public function __toString()
    {
        return rtrim(sprintf('phone:%s,%s,%s,%s',
            implode(',', $this->countries),
            implode(',', $this->types),
            $this->detect ? 'AUTO' : '',
            $this->lenient ? 'LENIENT' : ''
        ), ',');
    }
}