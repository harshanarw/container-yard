<?php

namespace App\View\Components;

use Illuminate\View\Component;

class DocumentManager extends Component
{
    public string $modelType;
    public int    $modelId;
    public string $folder;
    public string $accept;
    public int    $maxFiles;
    public string $title;
    public bool   $showLabel;

    public function __construct(
        string $modelType,
        int    $modelId,
        string $folder      = '',
        string $accept      = 'image/*,application/pdf,.doc,.docx,.xls,.xlsx',
        int    $maxFiles    = 10,
        string $title       = 'Documents & Photos',
        bool   $showLabel   = false,
    ) {
        $this->modelType = $modelType;
        $this->modelId   = $modelId;
        $this->folder    = $folder ?: strtolower(class_basename($modelType)) . 's/' . $modelId;
        $this->accept    = $accept;
        $this->maxFiles  = $maxFiles;
        $this->title     = $title;
        $this->showLabel = $showLabel;
    }

    public function render()
    {
        $documents = ($this->modelType)::find($this->modelId)?->documents ?? collect();

        return view('components.document-manager', ['documents' => $documents]);
    }
}
