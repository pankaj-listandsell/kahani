@extends('layouts.admin')
@section('title', 'New Story')

@section('content')
<div class="max-w-2xl">
    <a href="{{ route('admin.stories.index') }}" class="text-sm text-slate-500 hover:text-rose-700">← Back</a>
    <h2 class="text-xl font-bold mt-2 mb-6">New Story</h2>

    <form method="POST" action="{{ route('admin.stories.store') }}" enctype="multipart/form-data"
          class="bg-white rounded-xl border border-slate-200 p-6 space-y-4">
        @csrf
        @include('admin.stories._fields', ['story' => null])

        <button class="bg-rose-600 hover:bg-rose-700 text-white font-medium rounded-lg px-5 py-2.5">
            Save Story
        </button>
    </form>
</div>
@endsection
