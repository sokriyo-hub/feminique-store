@include('errors.plain', [
    'code' => '503',
    'title' => __('We will be right back'),
    'message' => __('The shop is being updated for a short while. Please check again in a few minutes.'),
    'button' => __('Try again'),
])