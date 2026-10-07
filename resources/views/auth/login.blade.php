<form method="POST" action="{{ route(\"login.post\") }}">@csrf<input name="email" type="email"><input name="password" type="password"><button type="submit">Login</button></form>
