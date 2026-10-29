<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    public function index(): View
    {
        $feedback = Feedback::query()
            ->with('user:id,name')
            ->latest()
            ->paginate(15);

        return view('admin.feedback.index', [
            'feedback' => $feedback,
        ]);
    }
}
