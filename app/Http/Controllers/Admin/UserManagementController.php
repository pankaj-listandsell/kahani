<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index()
    {
        $users = User::orderBy('id')->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        return view('admin.users.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role'     => ['required', Rule::in(['admin', 'user'])],
        ]);

        User::create($validated);

        return redirect()->route('admin.users.index')
            ->with('success', "User '{$validated['name']}' ban gaya.");
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'role'     => ['required', Rule::in(['admin', 'user'])],
        ]);

        // Khud ko admin se hata kar lock-out mat hone do.
        if ($user->id === Auth::id() && $validated['role'] !== 'admin') {
            return back()->withErrors(['role' => 'Aap apna khud ka admin role nahi hata sakte.']);
        }

        $payload = [
            'name'  => $validated['name'],
            'email' => $validated['email'],
            'role'  => $validated['role'],
        ];

        if (! empty($validated['password'])) {
            $payload['password'] = $validated['password'];
        }

        $user->update($payload);

        return redirect()->route('admin.users.index')
            ->with('success', "User '{$user->name}' update ho gaya.");
    }

    public function destroy(User $user)
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'Aap apna khud ka account delete nahi kar sakte.');
        }

        $name = $user->name;
        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', "User '{$name}' delete ho gaya.");
    }

    /**
     * Kisi user ki tarah login karo (impersonate) — baad me admin par wapas aa sakte ho.
     */
    public function loginAs(Request $request, User $user)
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'Aap already isi user se logged-in ho.');
        }

        $adminId = Auth::id();
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('impersonator_id', $adminId);

        return redirect()->route('admin.dashboard')
            ->with('success', "Ab aap '{$user->name}' ki tarah logged-in ho.");
    }

    /**
     * Impersonation khatam — apne admin account par wapas aao.
     */
    public function returnToAdmin(Request $request)
    {
        $adminId = $request->session()->pull('impersonator_id');

        if (! $adminId) {
            return redirect()->route('login');
        }

        $admin = User::find($adminId);
        if (! $admin) {
            Auth::logout();

            return redirect()->route('login');
        }

        Auth::login($admin);
        $request->session()->regenerate();

        return redirect()->route('admin.users.index')
            ->with('success', 'Wapas apne admin account par aa gaye.');
    }
}
