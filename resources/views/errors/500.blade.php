@include('errors.plain', [
    'code' => '500',
    'title' => __('Something went wrong'),
    'message' => __('We hit an unexpected problem. It is not your fault. Please try again in a little while.'),
    'button' => __('Back to home'),
])