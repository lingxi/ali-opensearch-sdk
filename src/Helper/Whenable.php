<?php

namespace Lingxi\AliOpenSearch\Helper;

trait Whenable
{
    /**
     * Apply the callback if the value is truthy.
     *
     * @param  bool  $value
     * @param  callable  $callback
     * @param  callable  $default
     * @return mixed
     */
    public function when($value, $callback, $default = null)
    {
        if ($value) {
            return call_user_func($callback, $this);
        } elseif ($default) {
            return call_user_func($default, $this);
        }

        return $this;
    }
}
