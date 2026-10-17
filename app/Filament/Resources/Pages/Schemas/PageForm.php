<?php

namespace App\Filament\Resources\Pages\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Fixed. This is the web link of the page and cannot be changed.'),
                RichEditor::make('content')
                    ->disableToolbarButtons(['attachFiles'])
                    ->columnSpanFull(),
            ]);
    }
}