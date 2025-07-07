<?php

namespace App\Http\Requests;

use App\Enums\HttpStatusCode;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\HttpResponseException;

trait CustomFailedValidationHandler
{
    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException If the request wants JSON,
     * a JSON error response is returned.
     * @throws \Illuminate\Validation\ValidationException If the request does not want JSON,
     * a redirect back with errors is performed.
     */
    protected function failedValidation(Validator $validator) {
        if($this->wantsJson()){
            $message = 'Validation failed!';
            $errors = $validator->errors();
            throw new HttpResponseException(response()->error($message, HttpStatusCode::UnprocessableEntity->value, $errors));
        }else{
            throw (new ValidationException($validator))
                ->errorBag($this->errorBag)
                ->redirectTo($this->getRedirectUrl());
        }
    }
}
