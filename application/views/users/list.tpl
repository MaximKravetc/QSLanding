<!doctype html>
<html lang="en" class="perfect-scrollbar-off">
<head>
	<meta charset="utf-8" />
	<link rel="apple-touch-icon" sizes="76x76" href="/s/admin/assets/img/apple-icon.png">
	<link rel="icon" type="image/png" sizes="96x96" href="/s/admin/assets/img/favicon.png">
	<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
	<title>Quantum system admin</title>
	<meta content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0' name='viewport' />
	<meta name="viewport" content="width=device-width" />
	<link href="/s/admin/css/style.min.css?v=1515107222" rel="stylesheet" />
	<script src="/s/admin/js/main.js?v=1518624095"></script>
</head>
<body>
<div class="wrapper">
	<div class="sidebar" data-background-color="white" data-active-color="danger">
		<div class="logo">
			<a href="/" class="simple-text logo-mini">
				ADM
			</a>
			<a href="/" class="simple-text logo-normal">
				Quantum System
			</a>
		</div>
		<div class="sidebar-wrapper">
			<ul class="nav">
				<li{if $user_params.controller == 'users'} class="active"{/if}>
					<a href="{$Url->c('users')->a('list')}">
						<i class="ti-user"></i>
						<p>Пользователи</p>
					</a>
				</li>
			</ul>
		</div>
	</div>
	<div class="main-panel">
		<nav class="navbar navbar-default">
			<div class="container-fluid">
				<div class="navbar-minimize">
					<button id="minimizeSidebar" class="btn btn-fill btn-icon"><i class="ti-more-alt"></i></button>
				</div>
				<div class="navbar-header">
					<button type="button" class="navbar-toggle">
						<span class="sr-only">Toggle navigation</span>
						<span class="icon-bar bar1"></span>
						<span class="icon-bar bar2"></span>
						<span class="icon-bar bar3"></span>
					</button>
					<span class="navbar-brand" href="#extendedtables">
                    	{switch $user_params.controller}
							{case 'users'}
								Пользователи
							{/case}
						{/switch}
					</span>
				</div>
			</div>
		</nav>
		<div class="content">
			<div class="container-fluid">
				<div class="row">
					<div class="col-md-12">
						<div class="card">
							<div class="card-content">
								<div class="fresh-datatables">
									<table id="datatables" class="table table-striped table-no-bordered table-hover" cellspacing="0" width="100%" style="width:100%">
										<thead>
											<tr>
												<th>Id</th>
												<th>Имя</th>
												<th>Почта</th>
												<th>Телефон</th>
												<th>Дата добавления</th>
												<th>IP</th>
												<th>User agent</th>
											</tr>
										</thead>
										<tfoot>
											<tr>
												<th>Id</th>
												<th>Имя</th>
												<th>Почта</th>
												<th>Телефон</th>
												<th>Дата добавления</th>
												<th>IP</th>
												<th>User agent</th>
											</tr>
										</tfoot>
									</table>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<footer class="footer">
			<div class="container-fluid">
				&copy; <script>document.write(new Date().getFullYear())</script>
			</div>
		</footer>
	</div>
</div>
</body>
{if $user_params.controller == 'users'}
{literal}
	<script type="text/javascript">
        $(document).ready(function() {
            $('#datatables').DataTable({
                pagingType: "full_numbers",
                lengthMenu: [
                    [10, 25, 50],
                    [10, 25, 50]
                ],
                ajax: {
                    url: '/ajax/users',
                    type: 'POST',
                    dataFilter: function(data){
                        var json = jQuery.parseJSON( data );

                        return JSON.stringify( json ); // return JSON string
                    }
                },
                deferRender: true,
                order: [[0, 'desc']],
                responsive: true,
                language: {
                    "search": "_INPUT_",
                    "searchPlaceholder": "Поиск пользователя"
                }
            });
        });
	</script>
{/literal}
{/if}
</html>
