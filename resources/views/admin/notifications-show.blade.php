@extends('layouts.dashboard')

@section('title', 'Message')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-white rounded-lg shadow-sm border border-gray-100">
        <div class="flex">
            <!-- Left: Message list (small) -->
            <div class="w-1/3 border-r border-gray-100 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Inbox</h2>
                <div class="space-y-3">
                    @php
                        $others = \App\Models\ContactMessage::orderBy('created_at','desc')->limit(10)->get();
                    @endphp
                    @foreach($others as $m)
                        <a href="{{ route('admin.notifications.show', $m->id) }}" class="block p-3 rounded-lg hover:bg-gray-50 transition-colors">
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">{{ strtoupper(substr($m->name ?: 'U',0,1)) }}</div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-sm font-medium text-gray-900 truncate">{{ $m->name }}</h3>
                                        <time class="text-xs text-gray-400">{{ $m->created_at->format('M d') }}</time>
                                    </div>
                                    <p class="text-sm text-gray-600 truncate mt-1">{{ \Illuminate\Support\Str::limit($m->message, 80) }}</p>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>

            <!-- Right: Message detail -->
            <div class="flex-1 p-8">
                <div class="max-w-2xl mx-auto">
                    <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-gray-500">From</p>
                                <p class="text-lg font-semibold text-gray-900">{{ $message->name }} &lt;{{ $message->email }}&gt;</p>
                                <p class="text-sm text-gray-400">Sent: {{ $message->created_at->toDayDateTimeString() }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <a href="{{ route('admin.notifications') }}" class="text-sm text-gray-600 hover:underline">Back</a>
                            </div>
                        </div>

                        <hr class="my-4">

                        <h3 class="text-lg font-semibold text-gray-900">{{ $message->subject }}</h3>

                        <div class="mt-4 text-gray-700 whitespace-pre-wrap">
                            {!! nl2br(e($message->message)) !!}
                        </div>

                        <div class="mt-6 flex items-center justify-end gap-3">
                            <a href="mailto:{{ $message->email }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Reply</a>
                            <a href="{{ route('admin.notifications') }}" class="px-4 py-2 bg-white text-gray-700 border border-gray-200 rounded-lg">Close</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
