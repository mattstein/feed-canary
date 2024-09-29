@extends('mail.layouts.default')

@section('content')
  <p>Your feed at {{ $feed->url }} is fixed!</p>

  <p>You can delete these notifications here if you don’t want them anymore: <a href="{{ $feed->manageUrl() }}">{{ $feed->manageUrl() }}</a></p>
@endsection