<?php

// class _masController extends Front
// {
//     public function init()
//     {
//       header("Access-Control-Allow-Origin: *");
//       header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, x-api-key");
//       header("Access-Control-Allow-Methods: GET, POST, PUT");
//       header("Content-Type: application/json; charset=UTF-8");
//     }
//
//     public function index(){
//         header('Content-Type: application/json');
//
//
//         $file = DOCROOT."application/views/be/assets/app-assets/images/logo/logo-iklh-24.png";
//         list($x, $y, $puzzleWidth, $puzzleHeight, $width, $height) = $this->generateCaptcha($file, UPLOADFOLDER.'captcha/puzzle_piece.png');
//
//         $response = [
//             'puzzle_piece_url' => 'https://ppkl.menlhk.go.id/iklh/uploads/captcha/puzzle_piece.png',
//             'position' => $x,
//             'position_y' => $y,
//             'puzzle_width' => $puzzleWidth,
//             'puzzle_height' => $puzzleHeight,
//             'bg_width' => $width,
//             'bg_height' => $height
//         ];
//
//         echo json_encode($response);
//     }
//
//     public function generateCaptcha($imagePath, $puzzlePiecePath) {
//         $image = imagecreatefrompng($imagePath);
//         $width = imagesx($image);
//         $height = imagesy($image);
//         $puzzleWidth = $width / 4;
//         $puzzleHeight = $height / 4;
//
//         $x = rand(0, $width - $puzzleWidth);
//         $y = rand(0, $height - $puzzleHeight);
//
//         $puzzlePiece = imagecreatetruecolor($puzzleWidth, $puzzleHeight);
//         imagecopy($puzzlePiece, $image, 0, 0, $x, $y, $puzzleWidth, $puzzleHeight);
//         imagepng($puzzlePiece, $puzzlePiecePath);
//
//         imagedestroy($image);
//         imagedestroy($puzzlePiece);
//
//         return [$x, $y, $puzzleWidth, $puzzleHeight, $width, $height];
//     }
//
//     public function verify(){
//       $data = json_decode(file_get_contents('php://input'), true);
//
//       $userPosition = (int)$data['position'];
//       $correctPosition = (int)$data['correct_position'];
//
//       $response = [
//           'status' => abs($userPosition - $correctPosition) < 10 ? 'success' : 'failure'
//       ];
//
//       echo json_encode($response);
//     }
}
?>
