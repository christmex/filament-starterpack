<?php

namespace App\Filament\Clusters\Settings\Resources\SubjectResource\Pages;

use App\Filament\Clusters\Settings\Resources\SubjectResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageSubjects extends ManageRecords
{
    protected static string $resource = SubjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
