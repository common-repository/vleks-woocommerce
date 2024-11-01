<?php

final class Vleks_Weight {

    /**
     * Scale
     *
     * @var int
     */
    const SCALE = 2;

    /**
     * Units
     *
     * @var array
     */
    protected $units = array (
        'kg'  => '1000',
        'g'   => '1',
        'lbs' => '453.59237',
        'oz'  => '28.3495231'
    );

    /**
     * Base
     *
     * @var string
     */
    private $base = '0.00';

    /**
     * Set calculatable amount of units
     *
     * @param   number      $amount
     * @param   string      $unit
     * @return  void
     */
    public function set ($amount, $unit)
    {
        if (!is_numeric ($amount)) {
            throw new InvalidArgumentException ('Invalid amount value set.');
        }

        if (!in_array ($unit, array_keys ($this->units), TRUE)) {
            throw new InvalidArgumentException ('Unknown unit provided.');
        }

        $this->base = bcmul ($amount, $this->units[$unit], self::SCALE);
    }

    /**
     * Get converted amount of units
     *
     * @param   string      $unit
     * @param   number      $decimals
     * @return  number
     */
    public function get ($unit, $decimals = 2)
    {
        if (!in_array ($unit, array_keys ($this->units), TRUE)) {
            throw new InvalidArgumentException ('Unknown unit provided.');
        }

        return round (bcmul ($this->base, $this->units[$unit], self::SCALE), $decimals, PHP_ROUND_HALF_UP);
    }
}
