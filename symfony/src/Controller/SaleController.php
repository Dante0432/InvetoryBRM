<?php

namespace App\Controller;

use App\Entity\Sale;
use App\Form\SaleType;
use App\Repository\SaleRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * @Route("/sale")
 */
class SaleController extends AbstractController
{
    /**
     * @Route("/", name="sale_index", methods={"GET"})
     */
    public function index(SaleRepository $saleRepository): Response
    {
        return $this->render('sale/index.html.twig', [
            'sales' => $saleRepository->findAll(),
        ]);
    }

    /**
    * @Route("/{id}/toggleCancelle", name="sale_cancelle", methods={"GET","POST"})
    */
    public function toggleCancelle(SaleRepository $saleRepository, $id): Response
    {
    $sale = $saleRepository->findOneBy(['id' => $id]);
    $sale->setCancelled(!$sale->getCancelled());
    $entityManager = $this->getDoctrine()->getManager();
    $entityManager->persist($sale);
    $entityManager->flush();
    return $this->redirectToRoute('sale_index');

    }

    /**
     * @Route("/{id}/invoice", name="sale_invoice", methods={"GET","POST"})
     */
    public function invoice(SaleRepository $saleRepository, Sale $sale): Response
    {
        // Configure Dompdf según sus necesidades
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Robooto');
        
        // Crea una instancia de Dompdf con nuestras opciones
        $dompdf = new Dompdf($pdfOptions);
        
        // Recupere el HTML generado en nuestro archivo twig
        $html = $this->renderView('sale/invoice.html.twig', [
            'sale' => $sale,
        ]);
        
        // Cargar HTML en Dompdf
        $dompdf->loadHtml($html);
        
        // (Opcional) Configure el tamaño del papel y la orientación 'vertical' o 'vertical'
        $dompdf->setPaper('A6', 'portrait');

        // Renderiza el HTML como PDF
        $dompdf->render();

        // Envíe el PDF generado al navegador (descarga forzada)
        $dompdf->stream("invoice_".$sale->getId()."_".date('dmyyyy').".pdf", [
            "Attachment" => true
        ]);
        return $this->redirectToRoute('sale_index');
    }

    /**
     * @Route("/new/{productId}", name="sale_new", methods={"GET","POST"})
     */
    public function new(Request $request,ProductRepository $productRepository,SaleRepository $saleRepository,$productId): Response
    {
        $currentUser=$this->getUser();
        $form = $this->createFormBuilder()
        ->add('quantity', IntegerType::class )
        ->add('Create', SubmitType::class, [
            'attr' => [
                'class' => 'btn btn-primary float-rigth mt-3'
            ]
        ])
        ->getForm();

        $product = $productRepository->findOneBy(['id' => $productId]);
        $oldSales = $saleRepository->findBy([
            'product' => $product,
            'cancelled' => false
        ]);
        $totalSold = false;
        foreach ($oldSales as $oldSale) {
            $totalSold += $oldSale->getQuantity();
        }
        $product->setQuantity($product->getQuantity()-$totalSold);

        $form->handleRequest($request);
        

        if ($form->isSubmitted() && $form->isValid()) {

            $data = $form->getData();
            $sale = new Sale();
            $sale->setProduct($product);
            $sale->setCancelled(false);
            $sale->setBuyer($currentUser);
            $sale->setQuantity($data['quantity']);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($sale);
            $entityManager->flush();
            return $this->redirectToRoute('product_index');
        }

        return $this->render('sale/new.html.twig', [
            'product' => $product,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="sale_show", methods={"GET"})
     */
    public function show(Sale $sale): Response
    {
        return $this->render('sale/show.html.twig', [
            'sale' => $sale,
        ]);
    }

    /**
     * @Route("/{id}/edit", name="sale_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, Sale $sale): Response
    {
        $form = $this->createForm(SaleType::class, $sale);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('sale_index');
        }

        return $this->render('sale/edit.html.twig', [
            'sale' => $sale,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="sale_delete", methods={"POST"})
     */
    public function delete(Request $request, Sale $sale): Response
    {
        if ($this->isCsrfTokenValid('delete'.$sale->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($sale);
            $entityManager->flush();
        }

        return $this->redirectToRoute('sale_index');
    }
}
