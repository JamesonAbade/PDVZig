<?php

namespace App\Controllers;

use App\Models\MeioPagamento;
use App\Models\Usuario;
use App\Models\Venda;
use App\Repositories\VendasDoDiaRepository;
use App\Rules\AcessoAoTipoDePdv;
use App\Rules\Logged;
use System\Controller\Controller;
use System\Get\Get;
use System\Post\Post;
use System\Session\Session;

class PdvPadraoController extends Controller
{
    protected $post;
    protected $get;
    protected $layout;
    protected $idEmpresa;
    protected $idUsuario;
    protected $idPerfilUsuarioLogado;

    public function __construct()
    {
        parent::__construct();
        $this->layout = 'default';

        $this->post = new Post();
        $this->get = new Get();
        $this->idEmpresa = Session::get('idEmpresa');
        $this->idUsuario = Session::get('idUsuario');
        $this->idPerfilUsuarioLogado = Session::get('idPerfil');

        $logged = new Logged();
        $logged->isValid();

        $acessoAoTipoDePdv = new AcessoAoTipoDePdv();
        $acessoAoTipoDePdv->validate();
    }

    public function index()
    {
        $vendasDoDiaRepository = new VendasDoDiaRepository();

        $vendasGeralDoDia = $vendasDoDiaRepository->vendasGeralDoDia($this->idEmpresa, 10);
        $totalVendasNoDia = $vendasDoDiaRepository->totalVendasNoDia($this->idEmpresa);

        $totalValorVendaPorMeioDePagamentoNoDia = $vendasDoDiaRepository->totalValorVendaPorMeioDePagamentoNoDia(
            $this->idEmpresa
        );

        $totalVendaNoDiaAnterior = $vendasDoDiaRepository->totalVendasNoDia(
            $this->idEmpresa, decrementDaysFromDate(1)
        );

        $meioPagamanto = new MeioPagamento();
        $meiosPagamentos = $meioPagamanto->all();

        $usuario = new Usuario();
        $usuarios = $usuario->usuarios($this->idEmpresa, $this->idPerfilUsuarioLogado);

        $this->view('pdv/padrao', $this->layout,
            compact(
                'vendasGeralDoDia',
                'meiosPagamentos',
                'usuarios',
                'totalVendasNoDia',
                'totalValorVendaPorMeioDePagamentoNoDia',
                'totalVendaNoDiaAnterior'
            ));
    }

    public function save()
    {
        if ($this->post->hasPost()) {
            $dados = (array)$this->post->data();
            $dados['id_empresa'] = $this->idEmpresa;

            # Preparar o valor da moeda para ser armazenado
            $dados['valor'] = formataValorMoedaParaGravacao($dados['valor']);

            /**
             * Gera um código unico de venda que será usado em todos os registros desse Loop
            */
            $dados['codigo_venda'] = uniqid(rand(), true).date('s').date('d.m.Y');

            try {
                $venda = new Venda();

                if (isset($dados['created_at'])) {
                    $createdAt = $dados['created_at'] . ' ' . date('H:i:s');
                    $d = new \DateTime();
                    $venda->useThisCreatedAt = $d->createFromFormat('d/m/Y H:i:s', $createdAt)->format('Y-m-d H:i:s');
                }

                $venda->save($dados);
                return $this->get->redirectTo("pdvPadrao");

            } catch (\Exception $e) {
                dd($e->getMessage());
            }
        }
    }

    public function desativarVenda($idVenda)
    {
        $venda = new Venda();
        try {
            $venda->update(['deleted_at' => timestamp()], $idVenda);
            echo json_encode(['status' => true]);

        } catch (\Exception $e) {
            dd($e->getMessage());
        }
    }
}
