<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Download Garad AI</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:Poppins,sans-serif;
}

body{
    background:linear-gradient(135deg,#020617,#0f172a,#1e293b);
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    padding:30px;
    color:#fff;
}

.card{
    width:520px;
    max-width:100%;
    background:#111827;
    border-radius:25px;
    padding:45px;
    text-align:center;
    border:1px solid rgba(255,255,255,.08);
    box-shadow:0 30px 70px rgba(0,0,0,.45);
}

.logo{
    width:95px;
    margin-bottom:20px;
}

h1{
    font-size:38px;
    font-weight:700;
    margin-bottom:10px;
}

.subtitle{
    color:#94a3b8;
    margin-bottom:35px;
    font-size:16px;
}

.info{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:15px;
    margin-bottom:35px;
}

.item{
    background:#1f2937;
    border-radius:15px;
    padding:18px;
}

.item h3{
    font-size:15px;
    margin-bottom:6px;
    color:#fff;
}

.item p{
    color:#cbd5e1;
    font-size:14px;
}

.button{
    display:block;
    width:100%;
    padding:18px;
    background:#10b981;
    color:#fff;
    text-decoration:none;
    font-size:18px;
    font-weight:700;
    border-radius:15px;
    transition:.3s;
}

.button:hover{
    background:#059669;
    transform:translateY(-2px);
}

.footer{
    margin-top:30px;
    color:#94a3b8;
    font-size:13px;
}

</style>

</head>

<body>

<div class="card">

    <img src="{{ asset('speaker/images/logo.png') }}" class="logo" alt="Garad AI Logo">

    <h1>Garad AI</h1>

    <div class="subtitle">
        Your Intelligent AI Assistant
    </div>

    <div class="info">

        <div class="item">
            <h3>Platform</h3>
            <p>Android</p>
        </div>

        <div class="item">
            <h3>Version</h3>
            <p>11.0.0</p>
        </div>

        <div class="item">
            <h3>Developer</h3>
            <p>Arkaan AI Center</p>
        </div>

        <div class="item">
            <h3>Price</h3>
            <p>Free</p>
        </div>

    </div>

    <a href="{{ route('download.apk') }}" class="button">
        ⬇ Download Garad AI
    </a>

    <div class="footer">
        © {{ date('Y') }} Garad AI • Powered by Arkaan AI Center
    </div>

</div>

</body>
</html>