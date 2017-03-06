<?php

namespace Lingxi\AliOpenSearch\Exception;

use Exception;

class OpensearchException extends Exception
{
    protected $errors;

    public function setErrors($errors)
    {
        $this->errors = $errors;
    }

    public function getErrors()
    {
        return $this->errors;
    }
}
