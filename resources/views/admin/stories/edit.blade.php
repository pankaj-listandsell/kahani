@extends('layouts.admin')
@section('title', 'Edit Story')

@section('content')
<div class="max-w-2xl">
    <a href="{{ route('admin.stories.show', $story) }}" class="text-sm text-slate-500 hover:text-rose-700">← Back</a>
    <h2 class="text-xl font-bold mt-2 mb-6">Edit Story</h2>

    <form method="POST" action="{{ route('admin.stories.update', $story) }}" enctype="multipart/form-data"
          class="bg-white rounded-xl border border-slate-200 p-6 space-y-4">
        @csrf
        @method('PUT')
        @include('admin.stories._fields', ['story' => $story])

        <button class="bg-rose-600 hover:bg-rose-700 text-white font-medium rounded-lg px-5 py-2.5">
            Save Changes
        </button>
    </form>
</div>
@endsection
