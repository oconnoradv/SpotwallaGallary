$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing
$root = Split-Path (Split-Path $PSScriptRoot -Parent) -Parent

function Brush($hex) {
    return [System.Drawing.SolidBrush]::new([System.Drawing.ColorTranslator]::FromHtml($hex))
}

function New-MapImage([int]$size) {
    $bitmap = [System.Drawing.Bitmap]::new($size, $size)
    $graphics = [System.Drawing.Graphics]::FromImage($bitmap)
    $graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
    $graphics.ScaleTransform($size / 256.0, $size / 256.0)
    $paper = Brush '#f6f3ec'
    $land = Brush '#e5e9dc'
    $fold = Brush '#eef0e5'
    $purple = Brush '#6941a5'
    $contour = [System.Drawing.Pen]::new([System.Drawing.ColorTranslator]::FromHtml('#c4ccba'), 2)
    $river = [System.Drawing.Pen]::new([System.Drawing.ColorTranslator]::FromHtml('#aacbd0'), 9)
    $edge = [System.Drawing.Pen]::new($paper.Color, 12)
    $route = [System.Drawing.Pen]::new($purple.Color, 6)
    $route.StartCap = $route.EndCap = [System.Drawing.Drawing2D.LineCap]::Round
    $edge.StartCap = $edge.EndCap = [System.Drawing.Drawing2D.LineCap]::Round
    $map = [System.Drawing.PointF[]]@(
        [System.Drawing.PointF]::new(20,40), [System.Drawing.PointF]::new(88,24),
        [System.Drawing.PointF]::new(168,42), [System.Drawing.PointF]::new(236,24),
        [System.Drawing.PointF]::new(236,216), [System.Drawing.PointF]::new(168,232),
        [System.Drawing.PointF]::new(88,214), [System.Drawing.PointF]::new(20,232)
    )
    $middle = [System.Drawing.PointF[]]@(
        [System.Drawing.PointF]::new(88,24), [System.Drawing.PointF]::new(168,42),
        [System.Drawing.PointF]::new(168,232), [System.Drawing.PointF]::new(88,214)
    )
    $track = [System.Drawing.Drawing2D.GraphicsPath]::new()
    $water = [System.Drawing.Drawing2D.GraphicsPath]::new()
    try {
        $graphics.Clear($paper.Color)
        $graphics.FillPolygon($land, $map)
        $graphics.FillPolygon($fold, $middle)
        $graphics.DrawPolygon($contour, $map)
        foreach ($y in @(82,120,162,204)) {
            $graphics.DrawBezier($contour, 20, $y, 70, ($y-35), 105, ($y+25), 150, ($y-10))
            $graphics.DrawBezier($contour, 150, ($y-10), 190, ($y-35), 214, ($y-20), 236, $y)
        }
        $graphics.DrawLine($contour, 88, 24, 88, 214)
        $graphics.DrawLine($contour, 168, 42, 168, 232)
        $water.AddBezier(38,32,112,68,58,104,150,146)
        $water.AddBezier(150,146,200,180,200,180,226,220)
        $graphics.DrawPath($river, $water)
        $track.AddBezier(48,190,62,152,104,174,116,132)
        $track.AddBezier(116,132,128,90,166,118,194,64)
        $graphics.DrawPath($edge, $track)
        $graphics.DrawPath($route, $track)
        $graphics.FillEllipse($purple, 37, 179, 22, 22)
        $graphics.FillEllipse($paper, 41, 183, 14, 14)
        $graphics.FillEllipse($purple, 174, 40, 40, 40)
        $graphics.FillPolygon($purple, [System.Drawing.PointF[]]@(
            [System.Drawing.PointF]::new(177,68), [System.Drawing.PointF]::new(211,68),
            [System.Drawing.PointF]::new(194,94)
        ))
        $graphics.FillEllipse($paper, 187, 53, 14, 14)
    } finally {
        foreach ($resource in @($graphics,$paper,$land,$fold,$purple,$contour,$river,$edge,$route,$track,$water)) {
            $resource.Dispose()
        }
    }
    return $bitmap
}

foreach ($size in @(128,256)) {
    $icon = New-MapImage $size
    try {
        $icon.Save((Join-Path $root ".wordpress-org\icon-$($size)x$size.jpg"), [System.Drawing.Imaging.ImageFormat]::Jpeg)
        if ($size -eq 256) {
            $icon.Save((Join-Path $root 'images\logo-256x256.jpg'), [System.Drawing.Imaging.ImageFormat]::Jpeg)
        }
    } finally { $icon.Dispose() }
}

foreach ($scale in @(1,2)) {
    $banner = [System.Drawing.Bitmap]::new((772*$scale), (250*$scale))
    $graphics = [System.Drawing.Graphics]::FromImage($banner)
    $graphics.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
    $graphics.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::AntiAliasGridFit
    $graphics.ScaleTransform($scale, $scale)
    $icon = New-MapImage 256
    $ink = Brush '#292337'
    $accent = Brush '#6941a5'
    $heading = [System.Drawing.Font]::new('Segoe UI', 27, [System.Drawing.FontStyle]::Bold, [System.Drawing.GraphicsUnit]::Pixel)
    $byline = [System.Drawing.Font]::new('Segoe UI', 18, [System.Drawing.FontStyle]::Italic, [System.Drawing.GraphicsUnit]::Pixel)
    try {
        $graphics.Clear([System.Drawing.ColorTranslator]::FromHtml('#f6f3ec'))
        $graphics.DrawImage($icon, 20, 20, 210, 210)
        $graphics.DrawString('Gallery for SpotWalla', $heading, $ink, 258, 88)
        $graphics.DrawString("an O'ConnorADV project", $byline, $accent, 260, 136)
        $banner.Save((Join-Path $root ".wordpress-org\banner-$(772*$scale)x$(250*$scale).jpg"), [System.Drawing.Imaging.ImageFormat]::Jpeg)
    } finally {
        foreach ($resource in @($banner,$graphics,$icon,$ink,$accent,$heading,$byline)) { $resource.Dispose() }
    }
}
