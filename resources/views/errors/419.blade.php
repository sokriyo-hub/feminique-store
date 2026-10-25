<x-error-page code="419"
              :title="__('Session expired')"
              :message="__('This page stayed open for too long. Please go back, refresh the page and try again.')"
              :retry="true" />