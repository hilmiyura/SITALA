<?php

/*
 * The MIT License
 *
 * Copyright (c) 2014 Toha <tohenk@yahoo.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
 * of the Software, and to permit persons to whom the Software is furnished to do
 * so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

//namespace NTLAB\Common;

class Terbilang
{
    const TERBILANG_SEPARATOR = ' ';
    const TERBILANG_SE = 'SE';
    const TERBILANG_PULUH = 'PULUH';
    const TERBILANG_BELAS = 'BELAS';
    const TERBILANG_RATUS = 'RATUS'; 

    const DECIMAL_SYMBOL = 1;
    const DECIMAL_COMMA = 2;
    const DECIMAL_PER = 3;
    const DECIMAL_CUSTOM = 4;

    protected $numbers = array('NOL', 'SATU', 'DUA', 'TIGA', 'EMPAT', 'LIMA', 'ENAM', 'TUJUH', 'DELAPAN', 'SEMBILAN');
    protected $hundreds = array('', self::TERBILANG_PULUH, self::TERBILANG_RATUS);
    protected $thousands = array('', 'RIBU', 'JUTA', 'MILYAR', 'TRILYUN', 'BILYUN');
    protected $decimal = null;

    /**
     * @var NTLAB\Common\Terbilang
     */
    protected static $instance = null;

    /**
     * Get instance.
     *
     * @return \NTLAB\Common\Terbilang
     */
    public static function getInstance()
    {
        if (null == static::$instance) {
            static::$instance = new self();
        }

        return static::$instance;
    }

    /**
     * Convert string number into number at specified index.
     *
     * @param string $str  Number string
     * @param int $index  The index
     * @return int
     */
    protected function digitIndex($str, $index)
    {
        return (int) substr($str, $index, 1);
    }

    /**
     * Remove leading zero.
     *
     * @param string $str  The number string
     * @return string name of zero removed
     */
    protected function trimZero(&$str)
    {
        $result = null;
        while (true) {
            if (strlen($str) <= 1 || $this->digitIndex($str, 0) !== 0) {
                break;
            }
            if (null !== $result) {
                $result .= static::TERBILANG_SEPARATOR;
            }
            $result .= $this->numbers[0];
            $str = substr($str, 1, strlen($str) - 1);
        }

        return $result;
    }

    /**
     * Concatenate angka with satuan.
     *
     * @param string $str  The number
     * @param string $satuan  Satuan
     * @return string
     */
    protected function concatSatuan($str, $satuan)
    {
        if ($str) {
            if ($str != static::TERBILANG_SE) {
                $str .= static::TERBILANG_SEPARATOR;
            }
            $str .= $satuan;
        }

        return $str;
    }

    /**
     * Process terbilang for value under a thousand.
     *
     * @param string $str
     *            The value
     * @return string
     */
    protected function terbilang3($str)
    {
        $result = null;
        $this->hundreds[1] = static::TERBILANG_PULUH;
        $this->trimZero($str);
        if (strlen($str)) {
            $len = strlen($str);
            $p = $len;
            $i = 0;
            while (true) {
                if ($i == $len) {
                    break;
                }
                $angka = $this->digitIndex($str, --$p);
                // translate number to its name
                $s = $angka > 0 ? $this->numbers[$angka] : null;
                // substitute 1 with 'SE' only when needed
                if ($i > 0 && $angka == 1) {
                    $s = static::TERBILANG_SE;
                }
                // check special number based on digit
                if ($i == 0 && $len > 1 && $this->digitIndex($str, $p - 1) == 1) {
                    if ($angka <= 1) {
                        $s = static::TERBILANG_SE;
                    }
                    if ($angka > 0) {
                        $this->hundreds[1] = static::TERBILANG_BELAS;
                    }
                    $i++;
                    $p--;
                }
                // Combine with satuan
                $s = $this->concatSatuan($s, $this->hundreds[$i]);
                // Combine with previous result
                $result = trim($s . static::TERBILANG_SEPARATOR . $result);
                $i++;
            }
        }

        return trim($result);
    }

    /**
     * Process terbilang.
     *
     * @param string $str
     *            The value
     * @param bool $leading_zero
     *            Include leading zero
     * @return string
     */
    public function terbilang($str, $leading_zero = false)
    {
        $result = null;
        $zero = $this->trimZero($str);
        // in case of 0
        if ($str == '0') {
            return $this->numbers[0];
        }
        $part = floor(strlen($str) / 3) + (strlen($str) % 3 > 0 ? 1 : 0);
        for ($i = 0; $i < $part; $i++) {
            $s = $this->terbilang3(substr($str, - 3));
            $str = substr($str, 0, strlen($str) - 3);
            // convert SATU RIBU to SERIBU
            if ($i == 1 && $s == $this->numbers[1]) {
                $s = static::TERBILANG_SE;
            }
            $s = $this->concatSatuan($s, $this->thousands[$i]);
            $result = trim($s.static::TERBILANG_SEPARATOR.$result);
        }
        if ($leading_zero && $zero) {
            $result = trim($zero.static::TERBILANG_SEPARATOR.$result);
        }

        return $result;
    }

    /**
     * Split the round and decimal part.
     *
     * @param string $str  The float number
     * @param string $number  The round part
     * @param string $decimal  The decimal part
     * @param string $separator  Decimal separator
     */
    protected function splitDecimal($str, &$number, &$decimal, $separator = '.')
    {
        $number = null;
        $decimal = null;
        if (false !== ($p = strpos($str, $separator))) {
            $number = substr($str, 0, $p);
            $decimal = substr($str, $p + 1);
        } else {
            $number = $str;
        }
    }

    /**
     * Set custom decimal separator.
     *
     * @param string $value  The decimal separator
     * @return \NTLAB\Common\Terbilang
     */
    public function setCustomDecimal($value)
    {
        $this->decimal = $value;

        return $this;
    }

    /**
     * Convert number as terbilang.
     *
     * @param mixed $value  The value to convert
     * @param bool $decimal  True to include decimal part
     * @param int $type  The decimal type
     * @return string
     */
    public function convert($value, $decimal = true, $type = self::DECIMAL_COMMA)
    {
        if (!is_string($value)) {
            $value = (string) $value;
        }
        $this->splitDecimal($value, $number, $frac);
        $result = $this->terbilang($number, false);
        if ($decimal && $frac) {
            switch ($type) {
                case static::DECIMAL_SYMBOL:
                    if ($fracs = $this->terbilang($frac, true)) {
                        $result .= ', '.$fracs;
                    }
                    break;

                case static::DECIMAL_COMMA:
                    if ($fracs = $this->terbilang($frac, true)) {
                        $result .= ' KOMA '.$fracs;
                    }
                    break;

                case static::DECIMAL_PER:
                    $result .= static::TERBILANG_SEPARATOR.$this->terbilang((string) (int) $frac, false);
                    $result .= ' PER '.$this->terbilang('1'.str_repeat('0', strlen($frac)), false);
                    break;

                case static::DECIMAL_CUSTOM:
                    if ($fracs = $this->terbilang($frac, true)) {
                        $result .= $this->decimal.$fracs;
                    }
                    break;
            }
        }

        return $result;
    }
}