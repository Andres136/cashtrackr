<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Translation\MessageSelector;

class SignInRequest extends FormRequest
{
   public function attributes(): array
    {
        return [
            
            'password' => 'contraseña',
        ];
    }
    
    public function messages(): array
    {
        return [
           
            'email.required' => 'El email es obligatorio.',
            'email.email' => 'El email no es valido.',
            'email.exists' => 'No encontramos una cuenta con ese correo electronico.',
            'password.required' => 'La contraseña es obligatoria.',
           
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
            'email' => ['required', 'email', 'exists:users,email'],
            'password' => ['required'],
            
        ];
    }
}
