

<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta content="width=device-width, initial-scale=1" name="viewport" />
	<title>@yield('title', 'acc263') | acc263</title>
	<!-- Favicon-->
	<link rel="icon" href="{{asset('assets/images/favicon.ico')}}" type="image/x-icon">
	<!-- Plugins Core Css -->
	<link href="{{asset('assets/css/common.min.css')}}" rel="stylesheet">
	<link href="{{asset('assets/fontawesome/css/fontawesome.css')}}" rel="stylesheet">
	<link href="{{asset('assets/fontawesome/css/solid.css')}}" rel="stylesheet">
	<!-- Custom Css -->
	<link href="{{asset('assets/css/style.css')}}" rel="stylesheet" />
	<link href="{{asset('assets/css/extra_pages.css')}}" rel="stylesheet" />
</head>

<body>

@yield('content')

</body>
<!-- Plugins Js -->
<script src="{{asset('assets/js/common.min.js')}}"></script>
	<!-- Extra page Js -->
	<script src="{{asset('assets/js/login-register.js')}}"></script>

</html>
