<?php

namespace App\Filament\Resources;

use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use App\Helpers\Helper;
use App\Models\Company;
use Filament\Forms\Get;
use Filament\Forms\Form;
use App\Models\Classroom;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Resources\Pages\Page;
use Illuminate\Support\HtmlString;
use Spatie\Permission\Models\Role;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Split;
use Illuminate\Support\Facades\Hash;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\SubNavigationPosition;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\TernaryFilter;
use App\Filament\Resources\UserResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use STS\FilamentImpersonate\Tables\Actions\Impersonate;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Resources\UserResource\RelationManagers;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;
use App\Filament\Resources\UserResource\RelationManagers\MainTeachersRelationManager;
use App\Filament\Resources\UserResource\RelationManagers\TeacherSubjectsRelationManager;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $modelLabel = 'Employee';

    protected static ?string $pluralModelLabel = 'Employees';
    // protected static ?string $navigationLabel = 'Employees';

    protected static SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Start;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Split::make([
                    Group::make([
                        Tabs::make('Label')
                        ->persistTabInQueryString()
                        ->tabs([
                            Tabs\Tab::make('User Login')
                                ->schema([
                                    TextInput::make('name')
                                        ->required()
                                        ->maxLength(255),
                                    TextInput::make('email')
                                        ->email()
                                        ->required()
                                        ->unique(ignoreRecord:true)
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('password')
                                        ->helperText(new HtmlString('Default Password <strong>(mantapjiwa00)</strong>'))
                                        ->default('mantapjiwa00')
                                        ->password()
                                        ->revealable()
                                        // ->required()
                                        ->maxLength(255)
                                        ->dehydrateStateUsing(fn (string $state): string => bcrypt($state))
                                        ->dehydrated(fn (?string $state): bool => filled($state))
                                        ->required(fn (string $operation): bool => $operation === 'create'),
                                    Forms\Components\Select::make('roles')
                                        ->relationship('roles', 'name')
                                        ->multiple()
                                        ->preload()
                                        ->live()
                                        ->searchable(),
                            ]),
                            Tabs\Tab::make('Details')
                                ->schema([
                                    TextInput::make('citizenship_number')
                                        ->unique(ignoreRecord:true)
                                        ->maxLength(255),
                                    TextInput::make('born_place'),
                                    DatePicker::make('born_date'),
                                    Select::make('company_id')
                                        ->label('Current Company')
                                        ->relationship('company','name')
                            ]),
                            Tabs\Tab::make('Status')
                                ->hiddenOn('create')
                                ->schema([
                                    DatePicker::make('bpjs_join_date'),
                                    DatePicker::make('jht_join_date'),
                                    DatePicker::make('kemnaker_join_date'),
                                    DatePicker::make('read_employee_terms_date'),
                                    Textarea::make('notes')->columnSpanFull(),
                                ]),
                            Tabs\Tab::make('Teacher Subject')
                                ->hiddenOn('edit')
                                ->visible(function(Get $get){
                                    return Role::whereIn('id',$get('roles'))->where('name',Helper::$userDependOnRoleTeacherSubject)->get()->count();
                                })
                                ->schema([
                                    Repeater::make('teacherSubjects')
                                        ->relationship()
                                        ->maxItems(function(string $operation){
                                            if($operation == 'create'){
                                                return 1;
                                            }
                                        })
                                        ->schema([
                                            Select::make('company_id')
                                                ->label('School')
                                                ->required()
                                                ->helperText('Only one subject for the first time, you can add more after you create the data.')
                                                ->relationship('company','name')
                                                ->unique(modifyRuleUsing: function (Unique $rule,$state, Get $get) {
                                                    return $rule
                                                            ->where('school_term', $get('school_term'))
                                                            ->where('school_year', $get('school_year'))
                                                            ->where('classroom_id', $get('classroom_id'))
                                                            ->where('subject_id', $get('subject_id'))
                                                            ;
                                                }, ignoreRecord:true)
                                                ->createOptionForm(CompanyResource::getForm()),
                                            Select::make('classroom_id')
                                                ->label('Classroom')
                                                ->required()
                                                ->relationship('classroom','name')
                                                ->unique(modifyRuleUsing: function (Unique $rule,$state, Get $get) {
                                                    return $rule
                                                            ->where('school_term', $get('school_term'))
                                                            ->where('school_year', $get('school_year'))
                                                            ->where('company_id', $get('company_id'))
                                                            ->where('subject_id', $get('subject_id'))
                                                            ;
                                                    }, ignoreRecord:true)
                                                    ->createOptionForm(ClassroomResource::getForm()),
                                            Select::make('subject_id')
                                                ->label('Subject')
                                                ->required()
                                                ->relationship('subject','name')
                                                ->unique(modifyRuleUsing: function (Unique $rule,$state, Get $get) {
                                                    return $rule
                                                            ->where('school_term', $get('school_term'))
                                                            ->where('school_year', $get('school_year'))
                                                            ->where('classroom_id', $get('classroom_id'))
                                                            ->where('company_id', $get('company_id'))
                                                            ;
                                                }, ignoreRecord:true)
                                                ->createOptionForm(CompanyResource::getForm()),
                                            Select::make('school_year')
                                                ->required()
                                                ->live(onBlur: true)
                                                ->options(fn()=>Helper::getSchoolYears()),
                                            Select::make('school_term')
                                                ->required()
                                                ->live(onBlur: true)
                                                ->options(fn()=>Helper::getTerms())
                                        ])
                                        ->columns(3)
                                        ->columnSpanFull()
                                ]),

                            Tabs\Tab::make('Main Teacher')
                                ->hiddenOn('edit')
                                ->visible(function(Get $get){
                                    return Role::whereIn('id',$get('roles'))->where('name',Helper::$userDependOnRoleMainTeacher)->get()->count();
                                })
                                ->schema([
                                    Repeater::make('mainTeachers')
                                        ->relationship()
                                        // ->disabled()
                                        // ->helperText(new HtmlString('Please Set User Role as <b>Main Teacher</b> first, if you want to enable this form.'))
                                        ->maxItems(function(string $operation){
                                            if($operation == 'create'){
                                                return 1;
                                            }
                                        })
                                        ->schema([
                                            Select::make('company_id')
                                                ->label('School')
                                                ->required()
                                                ->relationship('company','name')
                                                ->unique(modifyRuleUsing: function (Unique $rule,$state, Get $get) {
                                                    return $rule
                                                            ->where('school_term', $get('school_term'))
                                                            ->where('school_year', $get('school_year'))
                                                            ->where('classroom_id', $get('classroom_id'))
                                                            ;
                                                }, ignoreRecord:true)
                                                ->createOptionForm(CompanyResource::getForm()),
                                            Select::make('classroom_id')
                                                ->label('Classroom')
                                                ->required()
                                                ->relationship('classroom','name')
                                                // ->unique(modifyRuleUsing: function (Unique $rule, Get $get,$livewire, $state) {
                                                //     // dd($state);
                                                //     return $rule->where('school_year', $get('school_year'))
                                                //                 ->where('school_term', $get('school_term'))
                                                //                 ->where('user_id', $livewire->data['id'])
                                                //                 ->where('classroom_id', $state)
                                                //                 ;
                                                // })
                                                // ->unique(modifyRuleUsing: function (Unique $rule, Get $get, $state) {
                                                //     return($rule->where('school_year', $get('school_year'))
                                                //                 ->where('school_term', $get('school_term'))
                                                //                 );
                                                // }, ignoreRecord : true)
                                                ->unique(modifyRuleUsing: function (Unique $rule,$state, Get $get) {
                                                    // dd($livewire);
                                                    return $rule
                                                            ->where('school_term', $get('school_term'))
                                                            ->where('school_year', $get('school_year'))
                                                            ->where('company_id', $get('company_id'))
                                                            // ->where('classroom_id',$state)
                                                            ;
                                                    }, ignoreRecord:true)
                                                    ->createOptionForm(ClassroomResource::getForm()),
                                            Select::make('school_year')
                                                ->required()
                                                ->live(onBlur: true)
                                                ->options(fn()=>Helper::getSchoolYears()),
                                            Select::make('school_term')
                                                ->required()
                                                ->live(onBlur: true)
                                                ->options(fn()=>Helper::getTerms())
                                        ])
                                        ->columns(3)
                                        ->columnSpanFull()
                                        
                                ])
                        ])
                        ->columnSpanFull()
                        ->columns([
                            'sm' => 1,
                            'xl' => 2,
                        ]),
                        
                        
                    ]),

                    Group::make()
                        ->schema([
                            Forms\Components\Section::make('Employee Work Periode')
                                ->schema([
                                    DatePicker::make('join_date'),
                                    DatePicker::make('finish_contract'),
                                    DatePicker::make('permanent_date'),
                                ])
                                // ->columns(2)
                        ])
                        ->grow(false),
                ])
                ->from('md')
                ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(isIndividual:true,isGlobal:false),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(isIndividual:true,isGlobal:false),
                Tables\Columns\TextColumn::make('roles.name')
                    ->bulleted()
                    ->searchable(isIndividual:true,isGlobal:false),
                Tables\Columns\TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('born_place')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextInputColumn::make('born_date')
                ->type('date')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('citizenship_number')
                    ->searchable(isIndividual:true,isGlobal:false),
                Tables\Columns\TextColumn::make('company.name')
                    ->searchable(isIndividual:true,isGlobal:false),
                Tables\Columns\TextInputColumn::make('permanent_date')
                    ->type('date')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('join_date')
                    ->searchable(isIndividual:true,isGlobal:false)
                    ->sortable(),
                Tables\Columns\TextInputColumn::make('finish_contract')
                    ->type('date')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextInputColumn::make('bpjs_join_date')
                    ->type('date')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextInputColumn::make('jht_join_date')
                    ->type('date')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextInputColumn::make('kemnaker_join_date')
                    ->type('date')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextInputColumn::make('read_employee_terms_date')
                    ->type('date')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('notes')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    // ->dateTime()
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                DateRangeFilter::make('finish_contract')
                    ->ranges([
                        __('filament-daterangepicker-filter::message.today') => [now(), now()],
                        __('filament-daterangepicker-filter::message.yesterday') => [now()->subDay(), now()->subDay()],
                        __('filament-daterangepicker-filter::message.last_7_days') => [now()->subDays(6), now()],
                        __('filament-daterangepicker-filter::message.last_30_days') => [now()->subDays(29), now()],
                        __('filament-daterangepicker-filter::message.this_month') => [now()->startOfMonth(), now()->endOfMonth()],
                        'Next Month' => [now()->startOfMonth()->addMonth(), now()->endOfMonth()->addMonth()],
                        __('filament-daterangepicker-filter::message.last_month') => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
                        __('filament-daterangepicker-filter::message.this_year') => [now()->startOfYear(), now()->endOfYear()],
                        __('filament-daterangepicker-filter::message.last_year') => [now()->subYear()->startOfYear(), now()->subYear()->endOfYear()],
                    ])
                    ->withIndicator(),
                Tables\Filters\TrashedFilter::make(),
                SelectFilter::make('company_id')
                    // ->options(function(){
                    //     $getCompany = Company::all()->pluck('name','id')->toArray() + ['' => 'No Company'];
                    //     return $getCompany;
                    // })
                    ->label('Current Company')
                    ->searchable()
                    ->multiple()
                    ->preload()
                    ->relationship('company', 'name')
                    ,
                TernaryFilter::make('employee_status')
                    ->placeholder('All')
                    ->trueLabel('Only Permanent')
                    ->falseLabel('Only Cotract')
                    ->queries(
                        true: fn (Builder $query) => $query->where('finish_contract',NULL),
                        false: fn (Builder $query) => $query->where('finish_contract','!=',NULL),
                        blank: fn (Builder $query) => $query,
                    )
            ], layout: FiltersLayout::Modal)
            ->deferFilters()
            ->actions([
                Impersonate::make(),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                    Tables\Actions\ForceDeleteAction::make(),
                    Tables\Actions\RestoreAction::make(),
                ])
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }
    
    public static function getRelations(): array
    {
        return [
            MainTeachersRelationManager::class,
            TeacherSubjectsRelationManager::class,
            // MainTeachersRelationManager::make([
            //     'current_operation' => $livewire,
            // ]),
        ];
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
            // 'edit-status' => Pages\EditUserStatus::route('/{record}/edit/status'),
        ];
    }    

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            // Pages\EditUserStatus::class,
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getWidgets(): array
    {
        return [
            UserResource\Widgets\UserOverview::class,
        ];
    }


}
