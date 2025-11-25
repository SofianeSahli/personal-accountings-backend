<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Http\Resources\ProfileResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(private UserService $userService) {}

    public function create(UserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $this->userService->create($data);

        return response()->json($user, 201);
    }

    public function update(UserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();
        $user = $this->userService->update($user->id, $data);

        return response()->json(new ProfileResource($user));
    }

    public function get(Request $request): JsonResponse
    {
        $user = $this->userService->query(['auth_id' => $request->auth_id])->first();

        if (!$user) {
            return response()->json(['errors.user_not_found'], 404);
        }

        return response()->json($user);
    }
}
