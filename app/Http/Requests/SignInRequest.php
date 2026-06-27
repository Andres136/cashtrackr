<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Translation\MessageSelector;

class SignInRequest extends FormRequest
{
   public function attributes8(): array
    {
        return [
            
            'password' => 'contraseña',
        ];
    }
    
    public function messages(): array
    {
        return [
           
            'email.exists' => 'No encontramos una cuenta con ese correo electronico.',
           
        ];
    }
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email','exists:users,email'],
            'password' => ['required'],
            
        ];
    }
}
