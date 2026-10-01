@extends('layouts.admin')

@section('title', 'Live Chat')

@section('content')
  @include('admin.chats._workspace', ['chat' => null])
@endsection
