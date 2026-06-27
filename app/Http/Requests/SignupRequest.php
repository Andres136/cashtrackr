<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Psy\Output\PassthruPager;

class SignupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
   

    public function messages()
    {
        return [
            'name.required' => 'El campo nombre es obligatorio.',
            'email.required' => 'El campo email es obligatorio.',
            'email.email' => 'El campo email debe ser una dirección de correo electrónico válida.',
            'email.unique' => 'El correo electrónico ya está registrado.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => 'La contraseña debe tener al menos :min caracteres.',
            'password.letters'=>'la Contraseña debe tener al menos 1 letra',
            'password.mixed'=>'La Contraseña debe tener 1 letra mayuscula y letra minuscula',
            'password.symbols'=>'La contraseña debe tener al manos un caracter especial (@_*)',
            'password.numbers'=>'La Contraseña debe tener  al menos 1 numero',

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
             'name' => ['required', 'string'] ,
             'email' =>   ['required', 'email', 'unique:users,email'] ,
             'password' => ['required', 'confirmed',
              Password::min(4)
              ->letters()
              ->mixedCase()
              ->symbols()
              ->numbers()
              ->uncompromised()
              ] 
        ];
    }
}
