<?php namespace Propaganistas\LaravelPhone\Rules;

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

        $this->countries = static::parseCountries($countries);

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

        $this->types = static::parseTypesAsStrings($types);

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