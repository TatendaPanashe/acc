
@extends('layouts.authlayout')

@section('content')

<div class="loginmain">
		<div class="loginCard">
			<div class="login-btn splits">
				<p>Already an user?</p>
				<button class="active">Login</button>
			</div>
			@if(Route::has('register'))
			<div class="rgstr-btn splits">
				<p>Don't have an account?</p>
				<button>Register</button>
			</div>
			@endif
			<div class="wrapper">
				<form id="login" method="POST" action="{{ route('login') }}" tabindex="500">
				@csrf
				<div class=" image">
                                <img src="assets/images/hrlogo.png" class="user-img-style" alt="User Image" />
                            </div>
					<h3>Login</h3>
                    
					<div class="mail">
						<!-- <input type="email"> -->

						<input id="login-identifier" type="text" class=" @error('login') is-invalid @enderror" name="login" value="{{ old('login') }}" required autocomplete="username" autofocus>

@error('login')
	<span class="invalid-feedback" role="alert">
		<strong>{{ $message }}</strong>
	</span>
@enderror
						<label for="login-identifier">Email or Username</label>
					</div>
					<div class="passwd">
					<input id="password" type="password" class="@error('password') is-invalid @enderror" name="password" required autocomplete="current-password">

@error('password')
	<span class="invalid-feedback" role="alert">
		<strong>{{ $message }}</strong>
	</span>
@enderror
						<label>Password</label>
					</div>
					<div class="text-end p-t-8 p-b-31">
						<a href="#">
							Forgot password?
						</a>
					</div>
					<div class="submit">
						<button class="dark">Login</button>
					</div>
					<div class="flex-c p-b-112">
						<a href="#" class="login100-social-item bg-fb">
							<i class="fab fa-facebook-f"></i>
						</a>
						<a href="#" class="login100-social-item bg-twitter">
							<i class="fab fa-twitter"></i>
						</a>
						<a href="#" class="login100-social-item bg-google">
							<i class="fab fa-google"></i>
						</a>
					</div>
				</form>

				@if(Route::has('register'))
				<form id="register" method="POST" action="{{ route('register') }}" tabindex="502">
               
				@csrf
				<div class=" image">
                                <img src="assets/images/hrlogo.png" class="user-img-style" alt="User Image" />
                            </div>
					<h3>Apply</h3>
                    
					<div class="name">
					<input id="name" type="text"  name="name" value="{{ old('name') }}" required autocomplete="name" autofocus>

						<!-- <input type="text"> -->
						<label> Name</label>
					</div>
					<div class="mail">
						<!-- <input type="email"> -->
						<input id="email" type="email" class=" @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email">

						<label>Email</label>
					</div>
					<!-- <div class="uid">
						<input type="text">
						<label>Telephone</label>
					</div> -->
					<div class="passwd">
					<input id="password" type="password" class=" @error('password') is-invalid @enderror" name="password" required autocomplete="new-password">

						<!-- <input type="text"> -->
						<label>Password</label>
					</div>

					<div class="passwd">
					<input id="password-confirm" type="password" class="" name="password_confirmation" required autocomplete="new-password">
				
						<!-- <input type="text"> -->
						<label>Confirm Password</label>
					</div>
					<div class="submit">
						<button class="dark">Apply</button>
					</div>
				</form>
				@endif
			</div>
		</div>
	</div>

	@endsection
