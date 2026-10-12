<?php

namespace App\Modules\QuickTasks\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewQuickTaskRequest extends FormRequest
{
    public function authorize()
    {
        return true; 
    }

    public function rules()
    {
        return [
            'status_changed_to' => 'required|in:completed,rejected',
            'feedback_text' => 'nullable|string',
            'voice_record' => 'nullable|file',
            'attachment_file' => 'nullable|file',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->hasFile('voice_record')) {
                try {
                    \App\Modules\QuickTasks\Services\QuickTaskAttachmentSecurity::validate($this->file('voice_record'));
                } catch (\Illuminate\Validation\ValidationException $e) {
                    foreach ($e->errors() as $field => $messages) {
                        foreach ($messages as $msg) {
                            $validator->errors()->add($field, $msg);
                        }
                    }
                }
            }

            if ($this->hasFile('attachment_file')) {
                try {
                    \App\Modules\QuickTasks\Services\QuickTaskAttachmentSecurity::validate($this->file('attachment_file'));
                } catch (\Illuminate\Validation\ValidationException $e) {
                    foreach ($e->errors() as $field => $messages) {
                        foreach ($messages as $msg) {
                            $validator->errors()->add('attachment_file', $msg);
                        }
                    }
                }
            }
        });
    }
}