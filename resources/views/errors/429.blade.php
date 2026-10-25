<x-error-page code="429"
              :title="__('Too many requests')"
              :message="__('You are going a little too fast. Please wait a minute and try again.')"
              :retry="true" />