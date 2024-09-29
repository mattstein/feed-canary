@extends('mail.layouts.default')

@section('content')
  <p>Click to confirm your feed and start monitoring:</p>

  @include('mail.parts.button', [
    'label' => 'Confirm Feed',
    'url' => $feed->confirmUrl(),
  ])

  <p>If you have no idea why you’ve received this, please ignore it and sorry for the bother!</p>
@endsection
