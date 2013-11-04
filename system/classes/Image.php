<?php

    /**
     * @author Joshua Kissoon
     * @date 20121212
     * @description Class that handles Images
     */
    class Image
    {

       private $image, $width, $height, $resized_image;

       function __construct($filename = null)
       {
          /*
           * if image filename is passed, Load the image file then get the width and height of the image
           */
          if ($filename)
          {
             if (!$this->image = $this->loadImage($filename))
                return false;
             $this->width = imagesx($this->image);
             $this->height = imagesy($this->image);
          }
       }

       public function loadImage($file)
       {
          /*
           * Load the image file
           */
          $extension = strtolower(strrchr($file, '.'));   // Grab the image extension
          switch ($extension)
          {
             case '.jpg':
             case '.jpeg':
                $img = imagecreatefromjpeg($file);
                break;
             case '.gif':
                $img = imagecreatefromgif($file);
                break;
             case '.png':
                $img = imagecreatefrompng($file);
                break;
             default:
                $img = false;
                break;
          }
          return $img;
       }

       public function resizeImage($new_img_data = array(), $option = "crop")
       {
          /*
           * This is where we call the necessary resize Image function to resize the image
           * @params
           * $option - This parameter contains which resize option we want, options include:
           *  exact - If we want the exact image the exact size specified
           *  portrait - Scale the image to suit the vertical size specified
           *  landscape - Scale the image to suit the horizontal size specified
           *  crop -  Scale the image to suit the smaller axis(horiz or vertical),
           *          then crop out the edges of the larger axis to get the specified size
           */
          $new_width = @$new_img_data['width'];
          $new_height = @$new_img_data['height'];
          switch ($option)
          {
             case 'exact':
                $optimal_width = $new_width;
                $optimal_height = $new_height;
                break;
             case 'portrait':
                $optimal_width = $this->getSizeByFixedHeight($new_height);
                $optimal_height = $new_height;
                break;
             case 'landscape':
                $optimal_width = $new_width;
                $optimal_height = $this->getSizeByFixedWidth($new_width);
                break;
             case 'crop':
                $optionArray = $this->getOptimalCrop($new_width, $new_height);
                $optimal_width = $optionArray['optimal_width'];
                $optimal_height = $optionArray['optimal_height'];
                break;
          }

          /*
           * Here we create a canvas to put the image on using:
           *      imagecreatetruecolor() - returns an image identifier representing a black image of the specified size.
           * And then we copy our resampled image onto this canvas:
           *      imagecopyresampled() - copies a rectangular portion of one image to another image,
           *                          smoothly interpolating pixel values so that, in particular,
           *                          reducing the size of an image still retains a great deal of clarity
           */
          $this->resized_image = imagecreatetruecolor($optimal_width, $optimal_height);
          imagecopyresampled($this->resized_image, $this->image, 0, 0, 0, 0, $optimal_width, $optimal_height, $this->width, $this->height);

          /*
           * After we had the resampled image, and if they asked to crop the image,
           * Here we crop it
           */
          if ($option == 'crop')
          {
             $this->crop($optimal_width, $optimal_height, $new_width, $new_height);
          }
       }

       public function autoResizeImage($new_img_data = array())
       {
          if ($this->width > $this->height)
          {
             /*
              * If the image width > the height,
              * we take a landscape image
              */
             $this->resizeImage($new_img_data, "landscape");
             $this->resizeImage($new_img_data, "crop");
          }
          else if ($this->width < $this->height)
          {
             /*
              * If the image height > the width,
              * we take a portrait image
              */
             $this->resizeImage($new_img_data, "portrait");
             $this->resizeImage($new_img_data, "crop");
          }
          else
          {
             /*
              * if Width = height
              * we resize image to be exact
              */
             $this->resizeImage($new_img_data, "exact");
             $this->resizeImage($new_img_data, "crop");
          }
       }

       private function getSizeByFixedHeight($new_height)
       {
          $ratio = $this->width / $this->height;
          $new_width = $new_height * $ratio;
          return $new_width;
       }

       private function getSizeByFixedWidth($new_width)
       {
          $ratio = $this->height / $this->width;
          $new_height = $new_width * $ratio;
          return $new_height;
       }

       private function getOptimalCrop($new_width, $new_height)
       {
          $height_ratio = $this->height / $new_height;
          $width_ratio = $this->width / $new_width;

          /*
           * Calculate which axis is smaller and resize to suit the smaller axis,
           * since we would later on cut out the edges of the larger axis
           */
          if ($height_ratio < $width_ratio)
          {
             $optimal_ratio = $height_ratio;
          }
          else
          {
             $optimal_ratio = $width_ratio;
          }

          $optimal_height = $this->height / $optimal_ratio;
          $optimal_width = $this->width / $optimal_ratio;

          return array('optimal_width' => $optimal_width, 'optimal_height' => $optimal_height);
       }

       private function crop($optimal_width, $optimal_height, $new_width, $new_height)
       {
          /*
           * Here we find the center height and center of width to crop out the
           * edges of the longer side so we can have a square image
           */
          $crop_start_x = ( $optimal_width / 2) - ( $new_width / 2 );
          $crop_start_y = ( $optimal_height / 2) - ( $new_height / 2 );

          $crop = $this->resized_image;
          /* Here we start cropping to get the exact requested size */
          $this->resized_image = imagecreatetruecolor($new_width, $new_height);
          imagecopyresampled($this->resized_image, $crop, 0, 0, $crop_start_x, $crop_start_y, $new_width, $new_height, $new_width, $new_height);
       }

       public function saveImage($save_path, $image_quality = "100")
       {
          // *** Get extension
          $extension = strrchr($save_path, '.');
          $extension = strtolower($extension);

          switch ($extension)
          {
             case '.jpg':
             case '.jpeg':
                if (imagetypes() & IMG_JPG)
                {
                   imagejpeg($this->resized_image, $save_path, $image_quality);
                }
                break;

             case '.gif':
                if (imagetypes() & IMG_GIF)
                {
                   imagegif($this->resized_image, $save_path);
                }
                break;

             case '.png':
                /* We first invert the quality since 0 is best for png */
                $image_quality = 100 - $image_quality;
                /* PNG scale quality ranges from 0-9, so convert our quality to a value from 0-9 */
                $scale_quality = round(($image_quality / 100) * 9);

                if (imagetypes() & IMG_PNG)
                {
                   imagepng($this->resized_image, $save_path, $scale_quality);
                }
                break;
             default:
                /* Do some default operation */
                break;
          }
          /* Remove the image from memory after it has been saved */
          imagedestroy($this->resized_image);
       }

    }