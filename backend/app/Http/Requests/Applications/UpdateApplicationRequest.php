<?php

namespace App\Http\Requests\Applications;

class UpdateApplicationRequest extends StoreApplicationRequest
{
    public function authorize(): bool
    {
        $application = $this->route('application');

        return parent::authorize()
            && $application !== null
            && $application->student_id === $this->user()->getKey()
            && $application->isEditableByStudent();
    }
}