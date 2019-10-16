<?php
namespace App\Controller;

use App\Entity\EntityTransfert;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints as Assert;
use ZipArchive;

class transfertController extends AbstractController {

  // ############ INDEX ############
  /** @Route("/") */
  public function index(Request $request) {
      // Création d'un bouton de redirection vers la page de transfert
      $form = $this->createForm(FormType::class)
      ->add('send', SubmitType::class, [
        'label' => 'Commencer à transférer',
        'attr' => ['class' => 'my-3 btn btn-primary']
      ]);
      $form->handleRequest($request);

      if ($form->isSubmitted()) return $this->redirectToRoute('transfert');// Redirection vers la page de transfert de fichiers

      return $this->render('transfertIndex.html.twig', [ 'btn' => $form->createView() ]);// Affichage de la page d'index
  }


  // ############ transfert ############
  /** @Route("/transfert", name="transfert") */
  public function new(Request $request, \Swift_Mailer $mailer) {

      // Préparation de la création du formulaire de transfert
      $envoie = new EntityTransfert(); // Relier à l'entité EntityTransfert
      $formBuilder = $this->get('form.factory')->createBuilder(FormType::class, $envoie);

      // Création du formulaire et de tout ces composants
      $formBuilder
              ->add('name', TextType::class, [
                  'label'           => '',
                  'required'        => true,
                  'attr'            => ['class' => 'my-2 form-control', 'placeholder' => 'Nom de l\'expéditeur']
              ])
              ->add('sender', TextType::class, [
                  'label'           => '',
                  'required'        => true,
                  'attr'            => ['class' => 'my-2 form-control', 'placeholder' => 'Email de l\'expéditeur'],
                  'invalid_message' => 'Ce mail n\'est pas valide.',
                  'constraints'     => new Assert\Email()
                ])
                ->add('receiver', TextType::class, [
                    'label'           => '',
                    'required'        => true,
                    'attr'            => ['class' => 'my-2 form-control', 'placeholder' => 'Email du destinataire'],
                    'invalid_message' => 'Ce mail n\'est pas valide.',
                    'constraints'     => new Assert\Email()
                ])
                ->add('fileName', FileType::class, [
                    'label'           => 'Fichier à envoyer: ',
                    'attr'            => ['class' => 'my-3 form-control-file'],
                    'required'        => true
                ])
                ->add('send', SubmitType::class, [
                  'label' => 'Envoyer le fichier',
                  'attr'  => ['class' => 'my-3 btn btn-primary']
                ]);

      $form = $formBuilder->getForm();
      $form->handleRequest($request); // Redirection de l'envoie du formulaire sur la page du formulaire

      // Si la page actuel reçois le formulaire ...
      if ($form->isSubmitted() && $form->isValid()) {
        // On récupère les informations du formulaire
        $elements = $form->getData();
        $entityManager = $this->getDoctrine()->getManager();
        $originalName = $request->files->get('form')['fileName']->getClientOriginalName(); // Le nom original du fichier envoyer

        // Création du fichier ZIP qui contiendra le fichier
        $zipName = $elements->getName().'-'.uniqid() . '.zip';
        $zipfile = new ZipArchive();
        $zipfile->open($this->getParameter('file_directory') . $zipName, ZipArchive::CREATE);
        $zipfile->addFile($elements->getFileName(), $originalName);
        $zipfile->close();
        $elements->setFileName($zipName);

        // Ajout dans la base de donnée via l'entité
        $entityManager->persist($elements);
        $entityManager->flush();

        // Création du mail envoyer
        $message = (new \Swift_Message())
          ->setSubject($elements->getName() . ' vous à envoyé un fichier par WeFileTransfert.')
          ->setFrom(['wefiletransfert@gmail.com'])
          ->setTo([$elements->getReceiver()])
          ->setReplyTo([$elements->getSender()]);

        $message->setBody($this->renderView('email.html.twig', [
          'link' => 'uploads/' . $zipName,
          'name' => $elements->getName()
        ]),'text/html');
        $mailer->send($message); // Envoie de l'email

        return $this->render('transfertSuccess.html.twig', [
          'link' => 'uploads/' . $zipName
        ]); // Affichage du succès de l'envoie du formulaire
       }

      return $this->render('transfertPage.html.twig', ['form' => $form->createView()]); // Affichage du formulaire d'envoie de fichier
  }
}
