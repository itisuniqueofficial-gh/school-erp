<?php

declare(strict_types=1);

namespace Core\Routing;

use Core\Http\Request;
use Core\Http\Response;
use Core\Validation\Validator;
use Core\Validation\ValidationException;

/**
 * Base controller providing view rendering, JSON, redirect and validation helpers.
 */
abstract class Controller
{
    /**
     * Render a view within an HTTP response.
     *
     * @param array<string, mixed> $data
     */
    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        return Response::html(view($template, $data), $status);
    }

    /**
     * Return a JSON response.
     */
    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    /**
     * Redirect to a path, optionally flashing session data.
     *
     * @param array<string, mixed> $with
     */
    protected function redirect(string $path, array $with = []): Response
    {
        foreach ($with as $key => $value) {
            session()->flash($key, $value);
        }
        $location = str_starts_with($path, 'http') ? $path : url($path);
        return (new Response('', 302))->header('Location', $location);
    }

    /**
     * Redirect back to the previous page with flashed input and errors.
     *
     * @param array<string, string[]> $errors
     * @param array<string, mixed> $input
     */
    protected function back(array $errors = [], array $input = []): Response
    {
        if ($errors !== []) {
            session()->flash('_errors', $errors);
        }
        if ($input !== []) {
            session()->flash('_old_input', $input);
        }
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        return (new Response('', 302))->header('Location', $referer);
    }

    /**
     * Validate request input against rules; throws ValidationException on failure.
     *
     * @param array<string, string> $rules
     * @param array<string, string> $messages
     * @return array<string, mixed> The validated data.
     */
    protected function validate(Request $request, array $rules, array $messages = []): array
    {
        $validator = new Validator($request->all(), $rules, $messages);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                throw new ValidationException($validator->errors());
            }
            session()->flash('_errors', $validator->errors());
            session()->flash('_old_input', $request->except(['password', 'password_confirmation', '_token']));
            $referer = $_SERVER['HTTP_REFERER'] ?? '/';
            (new Response('', 302))->header('Location', $referer)->send();
            exit;
        }

        return $validator->validated();
    }

    /**
     * Standard JSON success envelope.
     */
    protected function success(mixed $data = null, string $message = 'OK', int $status = 200): Response
    {
        return Response::json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $status);
    }

    /**
     * Standard JSON error envelope.
     */
    protected function error(string $message, int $status = 400, mixed $errors = null): Response
    {
        return Response::json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }
}
