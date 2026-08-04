@extends('layouts.app')

@section('title', $nombreModulo)
@section('page-kicker', 'Módulo en planificación')
@section('page-title', $nombreModulo)

@section('content')
    <x-ui.planned-module
        :name="$nombreModulo"
        area="Módulo del alcance aprobado"
        status="planning"
        :dashboard-href="route('dashboard')"
        :back-href="url()->previous()"
    />
@endsection
