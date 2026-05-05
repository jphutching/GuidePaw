<?php
declare(strict_types=1);

function gpProfileImageDir(): string
{
    return dirname(__DIR__) . '/uploads/images';
}

function gpProfileImagePublicPath(string $filename): string
{
    return 'uploads/images/' . $filename;
}

function gpSaveCroppedProfileImage(string $field, ?string $existingPath, array &$errors): ?string
{
    $data = trim((string) ($_POST[$field] ?? ''));
    if ($data === '') {
        return $existingPath;
    }
    if (!preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,/', $data)) {
        $errors[] = 'Cropped image data was invalid.';
        return $existingPath;
    }
    $binary = base64_decode(substr($data, strpos($data, ',') + 1), true);
    if ($binary === false || strlen($binary) > 6 * 1024 * 1024) {
        $errors[] = 'Cropped image was too large or invalid.';
        return $existingPath;
    }
    $info = @getimagesizefromstring($binary);
    if (!$info || empty($info['mime'])) {
        $errors[] = 'Cropped image must be a valid image.';
        return $existingPath;
    }
    $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extMap[$info['mime']])) {
        $errors[] = 'Images must be JPG, PNG, or WebP.';
        return $existingPath;
    }
    $dir = gpProfileImageDir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extMap[$info['mime']];
    if (file_put_contents($dir . '/' . $filename, $binary) === false) {
        $errors[] = 'Could not save cropped image.';
        return $existingPath;
    }
    return gpProfileImagePublicPath($filename);
}

function gpProfileCropperScript(): string
{
    return <<<'HTML'
<script>
function gpInitProfileCroppers(){
  document.querySelectorAll('[data-crop-input]').forEach(function(input){
    if(input.dataset.cropReady==='1') return;
    input.dataset.cropReady='1';
    var target=document.querySelector(input.dataset.cropTarget);
    var preview=document.querySelector(input.dataset.cropPreview);
    var wrapper=input.closest('[data-crop-wrap]') || input.parentElement;
    var canvas=wrapper ? wrapper.querySelector('canvas[data-crop-canvas]') : null;
    var zoom=wrapper ? wrapper.querySelector('input[data-crop-zoom]') : null;
    var clear=wrapper ? wrapper.querySelector('[data-crop-clear]') : null;
    var ctx=canvas ? canvas.getContext('2d') : null;
    var img=new Image();
    var imageReady=false;
    var offsetX=0, offsetY=0, dragging=false, startX=0, startY=0;
    function draw(){
      if(!canvas || !ctx || !imageReady) return;
      var size=canvas.width;
      ctx.clearRect(0,0,size,size);
      var scale=Math.max(size/img.width, size/img.height) * parseFloat(zoom ? zoom.value : '1');
      var w=img.width*scale, h=img.height*scale;
      var x=(size-w)/2+offsetX, y=(size-h)/2+offsetY;
      var minX=size-w, maxX=0, minY=size-h, maxY=0;
      if(w>size) x=Math.min(maxX, Math.max(minX, x)); else x=(size-w)/2;
      if(h>size) y=Math.min(maxY, Math.max(minY, y)); else y=(size-h)/2;
      offsetX=x-(size-w)/2; offsetY=y-(size-h)/2;
      ctx.drawImage(img,x,y,w,h);
      var data=canvas.toDataURL('image/jpeg',0.88);
      if(target) target.value=data;
      if(preview) preview.src=data;
    }
    input.addEventListener('change', function(){
      var file=input.files && input.files[0];
      if(!file) return;
      var reader=new FileReader();
      reader.onload=function(e){
        img.onload=function(){
          imageReady=true; offsetX=0; offsetY=0;
          if(zoom) zoom.value='1';
          if(canvas) canvas.classList.remove('d-none');
          if(wrapper) wrapper.querySelectorAll('[data-crop-controls]').forEach(function(el){el.classList.remove('d-none');});
          draw();
        };
        img.src=e.target.result;
      };
      reader.readAsDataURL(file);
    });
    if(zoom) zoom.addEventListener('input', draw);
    if(canvas){
      canvas.addEventListener('pointerdown', function(e){dragging=true; startX=e.clientX; startY=e.clientY; canvas.setPointerCapture(e.pointerId);});
      canvas.addEventListener('pointermove', function(e){if(!dragging)return; offsetX += e.clientX-startX; offsetY += e.clientY-startY; startX=e.clientX; startY=e.clientY; draw();});
      canvas.addEventListener('pointerup', function(){dragging=false;});
      canvas.addEventListener('pointercancel', function(){dragging=false;});
    }
    if(clear) clear.addEventListener('click', function(){ if(target) target.value=''; input.value=''; if(canvas) canvas.classList.add('d-none'); if(wrapper) wrapper.querySelectorAll('[data-crop-controls]').forEach(function(el){el.classList.add('d-none');}); });
  });
}
if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', gpInitProfileCroppers); else gpInitProfileCroppers();
</script>
HTML;
}
