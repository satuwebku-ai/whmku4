@extends('layouts.admin')

@section('title', 'Chat — ' . $chat->display_name)

@section('content')
  @include('admin.chats._workspace')
@endsection
