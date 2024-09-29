@extends('mail.layouts.default')

@section('content')
  <p>Uh oh, your feed at {{ $feed->url }} seems broken.</p>

  <p>You can delete these notifications here if you don’t want them anymore: <a href="{{ $feed->manageUrl() }}">{{ $feed->manageUrl() }}</a></p>
@endsection
