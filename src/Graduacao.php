<?php

namespace Uspdev\Replicado;

class Graduacao
{
    public static function verifica($codpes, $codundclgi)
    {
        $query = " SELECT * FROM LOCALIZAPESSOA WHERE codpes = convert(int,:codpes)";
        $param = [
            'codpes' => $codpes,
        ];
        $result = DB::fetchAll($query, $param);

        if (!empty($result)) {
            foreach ($result as $row) {
                if (
                    trim($row['tipvin']) == 'ALUNOGR' &&
                    trim($row['sitatl']) == 'A' &&
                    trim($row['codundclg']) == $codundclgi
                ) {
                    return true;
                }

            }
        }
        return false;
    }

    /**
     * Método para retornar alunos ativos na unidade
     *
     * @param Int $condundclgi
     * @param String $partNome (optional)
     * @return array(campos tabela LOCALIZAPESSOA)
     */
    public static function ativos($codundclgi, $parteNome = null)
    {
        $param = [
            'codundclgi' => $codundclgi,
        ];
        $query = " SELECT LOCALIZAPESSOA.* FROM LOCALIZAPESSOA";
        $query .= " WHERE LOCALIZAPESSOA.tipvin = 'ALUNOGR' AND LOCALIZAPESSOA.codundclg = convert(int,:codundclgi)";
        if (!is_null($parteNome)) {
            $parteNome = trim(utf8_decode(Uteis::removeAcentos($parteNome)));
            $parteNome = strtoupper(str_replace(' ', '%', $parteNome));
            $query .= " AND nompesfon LIKE :parteNome";
            $param['parteNome'] = '%' . Uteis::fonetico($parteNome) . '%';
        }
        $query .= " ORDER BY nompes ASC";
        return DB::fetchAll($query, $param);
    }

    /**
     * Método para retornar dados do curso de um aluno na unidade
     *
     * @param Int $codpes
     * @param Int $codundclgi
     * @return array(codpes, nompes, codcur, nomcur, codhab, nomhab, dtainivin, codcurgrd)
     */
    public static function curso($codpes, $codundclgi)
    {
        $query = " SELECT L.codpes, L.nompes, C.codcur, C.nomcur, H.codhab, H.nomhab, V.dtainivin, V.codcurgrd";
        $query .= " FROM LOCALIZAPESSOA L";
        $query .= " INNER JOIN VINCULOPESSOAUSP V ON (L.codpes = V.codpes) AND (L.codundclg = V.codclg)";
        $query .= " INNER JOIN CURSOGR C ON (V.codcurgrd = C.codcur)";
        $query .= " INNER JOIN HABILITACAOGR H ON (H.codhab = V.codhab)";
        $query .= " WHERE (L.codpes = convert(int,:codpes))";
        $query .= " AND (L.tipvin = 'ALUNOGR' AND L.codundclg = convert(int,:codundclgi))";
        $query .= " AND (V.codcurgrd = H.codcur AND V.codhab = H.codhab)";
        $param = [
            'codpes' => $codpes,
            'codundclgi' => $codundclgi,
        ];
        return DB::fetch($query, $param);
    }

    /**
     * Recebe o nº USP do aluno *int* e retorna *int* com o código do programa
     *
     * @param Int $codpes
     * @return Int
     */

    public static function programa($codpes)
    {
        $query = " SELECT TOP 1 * FROM HISTPROGGR ";
        $query .= " WHERE (HISTPROGGR.codpes = convert(int,:codpes)) ";
        $query .= " AND (HISTPROGGR.stapgm = 'H' OR HISTPROGGR.stapgm = 'R') ";
        $query .= " ORDER BY HISTPROGGR.dtaoco DESC ";
        $param = [
            'codpes' => $codpes,
        ];
        return DB::fetch($query, $param);
    }

    /**
     * Retorna o nome do curso
     *
     * @param Int $codcur
     * @return String
     */

    public static function nomeCurso($codcur)
    {
        $query = " SELECT TOP 1 * FROM CURSOGR ";
        $query .= " WHERE (CURSOGR.codcur = convert(int, :codcur)) ";
        $param = [
            'codcur' => $codcur,
        ];
        $result = DB::fetch($query, $param);
        if ($result) {
            return $result['nomcur'];
        }

        return $result;
    }

    /**
     * Retorna o nome da habilitação
     *
     * @param Int $codcur
     * @param SmallInt $codhab
     * @return String
     */

    public static function nomeHabilitacao($codhab, $codcur)
    {
        $query = " SELECT TOP 1 * FROM HABILITACAOGR ";
        $query .= " WHERE (HABILITACAOGR.codhab = convert(int, :codhab) AND HABILITACAOGR.codcur = convert(int, :codcur)) ";
        $param = [
            'codhab' => $codhab,
            'codcur' => $codcur,
        ];
        $result = DB::fetch($query, $param);
        if ($result) {
            return $result['nomhab'];
        }

        return $result;
    }

    public static function obterCursosHabilitacoes($codundclgi)
    {
        $query = " SELECT CURSOGR.*, HABILITACAOGR.* FROM CURSOGR, HABILITACAOGR";
        $query .= " WHERE (CURSOGR.codclg = convert(int, :codundclgi)) AND (CURSOGR.codcur = HABILITACAOGR.codcur)";
        $query .= " AND ( (CURSOGR.dtaatvcur IS NOT NULL) AND (CURSOGR.dtadtvcur IS NULL) )";
        $query .= " AND ( (HABILITACAOGR.dtaatvhab IS NOT NULL) AND (HABILITACAOGR.dtadtvhab IS NULL) )";
        $query .= " ORDER BY CURSOGR.nomcur, HABILITACAOGR.nomhab ASC";
        $param = [
            'codundclgi' => $codundclgi,
        ];
        return DB::fetchAll($query, $param);
    }

    /**
     * Método para obter as disciplinas de graduação oferecidas na unidade
     *
     * @param Array $arrCoddis
     * @return void
     */
    public static function obterDisciplinas($arrCoddis)
    {
        $query = " SELECT D1.* FROM DISCIPLINAGR AS D1";
        $query .= " WHERE (D1.verdis = (
            SELECT MAX(D2.verdis) FROM DISCIPLINAGR AS D2 WHERE (D2.coddis = D1.coddis)
        )) AND ( ";
        foreach ($arrCoddis as $sgldis) {
            $query .= " (D1.coddis LIKE '$sgldis%') OR ";
        }
        $query = substr($query, 0, -3);
        $query .= " ) ";
        $query .= " ORDER BY D1.coddis ASC";
        return DB::fetchAll($query);
    }

    /**
     * Método para trazer o nome da disciplina de graduação
     *
     * @param String $coddis
     * @return void
     */
    public static function nomeDisciplina($coddis)
    {
        $query = " SELECT D1.* FROM DISCIPLINAGR AS D1";
        $query .= " WHERE (D1.verdis = (
            SELECT MAX(D2.verdis) FROM DISCIPLINAGR AS D2 WHERE (D2.coddis = D1.coddis)
        )) AND (D1.coddis = :coddis)";
        $param = [
            'coddis' => $coddis,
        ];
        $result = DB::fetch($query, $param);
        if ($result) {
            return $result['nomdis'];
        }

        return $result;
    }

    /**
     * Método para trazer as disciplinas, status e créditos concluídos
     *
     * @param Int $codpes
     * @return void
     */
    public static function disciplinasConcluidas($codpes, $codundclgi)
    {
        $programa = self::programa($codpes);
        $programa = $programa['codpgm'];
        $ingresso = self::curso($codpes, $codundclgi);
        $ingresso = substr($ingresso['dtainivin'], 0, 4);
        $query = "SELECT DISTINCT H.coddis, H.rstfim, D.creaul, D.cretrb FROM HISTESCOLARGR AS H, DISCIPLINAGR AS D
            WHERE H.coddis = D.coddis AND H.verdis = D.verdis AND H.codpes = convert(int, :codpes) AND H.codpgm = convert(int, :programa)
            AND	(H.codtur = '0' OR CONVERT(INT, CONVERT(CHAR(4), H.codtur)) >= YEAR(:ingresso))
            AND (H.rstfim = 'A' OR H.rstfim = 'D' OR (H.rstfim = NULL AND H.stamtr = 'M' AND H.codtur LIKE ':ingresso' + '1%'))
            ORDER BY H.coddis";
        $param = [
            'codpes' => $codpes,
            'programa' => $programa,
            'ingresso' => $ingresso,
            'ingresso' => $ingresso,
        ];
        return DB::fetchAll($query, $param);
    }

    /**
     * Método para trazer os créditos de uma disciplina
     *
     * @param string $coddis
     * @return int $creaul
     */
    public static function creditosDisciplina($coddis)
    {
        $query = " SELECT D1.creaul FROM DISCIPLINAGR AS D1";
        $query .= " WHERE (D1.verdis = (
            SELECT MAX(D2.verdis) FROM DISCIPLINAGR AS D2 WHERE (D2.coddis = D1.coddis)
        )) AND (D1.coddis = :coddis)";
        $param = [
            'coddis' => $coddis,
        ];
        $result = DB::fetch($query, $param);
        if ($result) {
            return $result['creaul'];
        }

        return $result;
    }

    /**
     * Créditos atribuídos por Aproveitamento de Estudos no exterior
     * Documentação da replicação: * credito-aula-atribuido
     *                             * creaulatb
     *                             * Número de Créditos aula, atribuído pelo órgão responsável,
     *                               a uma disciplina livre cursada no exterior por aluno da USP.
     * @param Int $codpes
     * @param Int $codundclgi
     * @return Array(coddis, creaulatb)
     */
    public static function creditosDisciplinasConcluidasAproveitamentoEstudosExterior($codpes, $codundclgi)
    {
        $programa = self::programa($codpes);
        $programa = $programa['codpgm'];
        $ingresso = self::curso($codpes, $codundclgi);
        $ingresso = substr($ingresso['dtainivin'], 0, 4);
        $query = "SELECT DISTINCT H.coddis, R.creaulatb ";
        $query .= "FROM HISTESCOLARGR AS H, DISCIPLINAGR AS D, REQUERHISTESC AS R ";
        $query .= "WHERE H.coddis = D.coddis AND H.verdis = D.verdis AND H.codpes = convert(int, :codpes) AND H.codpgm = convert(int, :programa) ";
        $query .= "AND H.coddis = R.coddis AND H.verdis = R.verdis AND H.codtur = R.codtur AND H.codpes = R.codpes ";
        $query .= "AND (H.rstfim = 'D') AND ((R.creaulatb IS NOT NULL) OR (R.creaulatb > 0)) ";
        $query .= "ORDER BY H.coddis";
        $param = [
            'codpes' => $codpes,
            'programa' => $programa,
        ];
        return DB::fetchAll($query, $param);
    }

    /**
     * Disciplinas (grade curricular) para um currículo atual no JúpiterWeb
     * a partir do código do curso e da habilitação
     *
     * @param String $codcur
     * @param Int $codhab
     * @return Array(coddis, nomdis, verdis, numsemidl, tipobg)
     */
    public static function disciplinasCurriculo($codcur, $codhab)
    {
        $query = "SELECT G.coddis, D.nomdis, G.verdis, G.numsemidl, G.tipobg ";
        $query .= " FROM GRADECURRICULAR G INNER JOIN DISCIPLINAGR D ON (G.coddis = D.coddis AND G.verdis = D.verdis)";
        $query .= " WHERE G.codcrl IN (SELECT TOP 1 codcrl";
        $query .= " FROM CURRICULOGR";
        $query .= " WHERE codcur = :codcur AND codhab = convert(int, :codhab)";
        $query .= " ORDER BY dtainicrl DESC)";
        $param = [
            'codcur' => $codcur,
            'codhab' => $codhab,
        ];
        return DB::fetchAll($query, $param);
    }

    /**
     * Disciplinas equivalentes de um currículo atual no JúpiterWeb
     * a partir do código do curso e da habilitação
     *
     * @param String $codcur
     * @param Int $codhab
     * @return Array(coddis, verdis, tipobg, coddis_equivalente, verdis_equivalente)
     */
    public static function disciplinasEquivalentesCurriculo($codcur, $codhab)
    {
        $query = "SELECT G.codeqv, G.coddis, G.verdis, GC.tipobg, E.coddis as coddis_eq, E.verdis as verdis_eq ";
        $query .= " FROM GRUPOEQUIVGR G INNER JOIN EQUIVALENCIAGR E ON (G.codeqv = E.codeqv) ";
        $query .= " INNER JOIN GRADECURRICULAR GC ON (GC.coddis = G.coddis AND GC.verdis = G.verdis AND G.codcrl = GC.codcrl)";
        $query .= " WHERE G.codcrl IN (SELECT TOP 1 codcrl";
        $query .= " FROM CURRICULOGR";
        $query .= " WHERE codcur = :codcur AND codhab = convert(int, :codhab)";
        $query .= " ORDER BY dtainicrl DESC)";
        $param = [
            'codcur' => $codcur,
            'codhab' => $codhab,
        ];
        return DB::fetchAll($query, $param);
    }

    /**
     * Departamento de Ensino do Aluno de Graduação
     *
     * @param Int $codpes
     * @param Int $codundclgi
     * @return Array(nomabvset)
     */
    public static function setorAluno($codpes, $codundclgi)
    {
        $codcur = self::curso($codpes, $codundclgi)['codcur'];
        $codhab = self::curso($codpes, $codundclgi)['codhab'];
        $query = " SELECT TOP 1 L.nomabvset FROM CURSOGRCOORDENADOR AS C
                    INNER JOIN LOCALIZAPESSOA AS L ON C.codpesdct = L.codpes
                    WHERE C.codcur = CONVERT(INT, :codcur) AND C.codhab = CONVERT(INT, :codhab)";
        $param = [
            'codcur' => $codcur,
            'codhab' => $codhab,
        ];
        $result = DB::fetch($query, $param);
        // Nota: Situação a se tratar com log de ocorrências
        // Se o departamento de ensino do alguno de graduação não foi encontrado
        if ($result == false) {
            // Será retornado 'DEPARTAMENTO NÃO ENCONTRADO' a fim de se detectar as situações ATÍPICAS em que isso ocorre
            $result = ['nomabvset' => 'DEPARTAMENTO NÃO ENCONTRADO'];
        }
        return $result;
    }

    /**
     * Método para retornar o total de alunos de graduação do gênero
     * e curso (opcional) especificado
     * @param Char $sexpes
     * @param Integer $codcur (optional)
     * @return void
     */
    public static function contarAtivosPorGenero($sexpes, $codcur = null)
    {
        $unidades = getenv('REPLICADO_CODUNDCLG');

        $query = " SELECT COUNT (DISTINCT LOCALIZAPESSOA.codpes) FROM LOCALIZAPESSOA
                    JOIN PESSOA ON PESSOA.codpes = LOCALIZAPESSOA.codpes
                    JOIN SITALUNOATIVOGR ON SITALUNOATIVOGR.codpes = LOCALIZAPESSOA.codpes
                    WHERE LOCALIZAPESSOA.tipvin = 'ALUNOGR'
                    AND LOCALIZAPESSOA.codundclg IN ({$unidades})
                    AND PESSOA.sexpes = :sexpes AND SITALUNOATIVOGR.codcur = convert(int,:codcur) ";
        $param = [
            'sexpes' => $sexpes,
            'codcur' => $codcur,
        ];
        return DB::fetch($query, $param)['computed'];
    }

    /**
     * Método para retornar se um codpes é coordenador de curso de graduação
     * @param Integer $codpes
     * @return boolean
     */
    public static function verificarCoordenadorCursoGrad(int $codpes)
    {
        $query = "SELECT COUNT(codpesdct) as qtde_cursos
                    FROM CURSOGRCOORDENADOR
                    WHERE codpesdct = convert(int, :codpes) AND (getdate() BETWEEN dtainicdn AND dtafimcdn)";

        $param = [
            'codpes' => $codpes,
        ];

        $result = DB::fetch($query, $param);

        if ($result['qtde_cursos'] > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Método para retornar se uma pessoa é graduada nos cursos da unidade
     * Retornará true caso tenha status Conclusão em um programa ou uma habilitação,
     * caso contrário, retornará false

     * @author Lucas Flóro 17/11/2020
     * @param Integer $codpes
     * @return boolean
     */
    public static function verificarPessoaGraduadaUnidade(int $codpes)
    {
        $cursos = implode(',', Graduacao::obterCodigosCursos());

        if (empty($cursos) || ($cursos == null)) {
            return false;
        }

        $query = "SELECT p.codpes
                    FROM PROGRAMAGR p INNER JOIN HABILPROGGR h ON (p.codpes = h.codpes AND p.codpgm = h.codpgm)
                    WHERE p.codpes = convert(int, :codpes)
                        AND (tipencpgm LIKE :tipencpgm OR tipenchab LIKE :tipenchab)
                        AND h.dtaclcgru IS NOT NULL
                        AND h.codcur IN ({$cursos})";
        $param = [
            'codpes' => $codpes,
            'tipencpgm' => 'Conclus_o',
            'tipenchab' => 'Conclus_o',
        ];

        $result = DB::fetch($query, $param);

        if ($result) {
            return true;
        }

        return false;
    }

    /*
     * Método que verifica através do número USP e código da unidade
     * se a pessoa é Ex-Aluna de Graduação ou não
     * retorna true se a pessoa for Ex-Aluna de Graduação USP
     * ou false, caso o contrário
     *
     * @param Integer $codpes : Número USP
     * @param Integer $codorg : Código da unidade
     * @return boolean
     */
    public static function verificarExAlunoGrad($codpes, $codorg)
    {
        $query = " SELECT codpes from TITULOPES
                    WHERE codpes = convert(int,:codpes)
                    AND codcur IS NOT NULL
                    AND codorg = convert(int,:codorg) ";
        $param = [
            'codpes' => $codpes,
            'codorg' => $codorg,
        ];
        $result = DB::fetch($query, $param);
        if (!empty($result)) {
            return true;
        }

        return false;
    }

    public static function obterGradeHoraria($codpes)
    {
        $current = date("Y") . (date("m") > 6 ? 2 : 1);

        $query = "SELECT h.coddis, h.codtur, o.diasmnocp, p.horent, p.horsai FROM HISTESCOLARGR h
                    INNER JOIN OCUPTURMA o ON (h.coddis = o.coddis AND h.codtur = o.codtur)
                    INNER JOIN PERIODOHORARIO p ON (o.codperhor = p.codperhor)
                    WHERE h.codpes = convert(int,:codpes) AND h.codtur LIKE '%{$current}%'";
        $param = [
            'codpes' => $codpes,
        ];
        return DB::fetchAll($query, $param);
    }

    /*
     * Retornar apenas códigos de curso de Graduação da unidade
     *
     * @author Lucas Flóro 20/04/2021
     * @return array
     */
    public static function obterCodigosCursos()
    {
        $codigo_unidade = getenv('REPLICADO_CODUNDCLG');

        $query = "SELECT codcur
                  FROM CURSOGR
                  WHERE codclg = convert(int,:codclg)";

        $param = [
            'codclg' => $codigo_unidade,
        ];

        $cursos = DB::fetchAll($query, $param);
        $codcur = array_column($cursos, 'codcur');
        return $codcur;
    }

    /**
     * Retorna lista das disciplinas de uma grade curricular
     *
     * @param Int $codcur: código do curso
     * @param Int $codhab: código da habilitação
     * @param Char $tipobg: tipo, exemplo: O-Obrigatória
     * @return Array
     * @author @thiagogomesverissimo 05/05/2021
     **/
    public static function listarDisciplinasGradeCurricular($codcur, $codhab, $tipobg = 'O')
    {
        $query = DB::getQuery('Graduacao.listarDisciplinasGradeCurricular.sql');
        $param = [
            'codcur' => $codcur,
            'codhab' => $codhab,
            'tipobg' => $tipobg,
        ];
        return DB::fetchAll($query, $param);
    }

    /**
     * Retorna lista com os intercâmbios internacionais ativos de alunos da Graduação,
     * com número USP do aluno, nome da Universidade e nome do país da Universidade
     *
     * @return Array
     * @author @gabrielareisg 28/05/2021
     **/
    public static function listarIntercambios()
    {
        $codundclgi = getenv('REPLICADO_CODUNDCLG');

        $query = DB::getQuery('Graduacao.listarIntercambios.sql');

        $query = str_replace('__codundclgi__', $codundclgi, $query);

        return DB::fetchAll($query);
    }

    /**
     * Dado um número USP de um aluno de Graduação retorna os dados sobre o intercâmbio do aluno,
     * como o nome da Universidade, nome do país da Universidade, data de início e data de término.
     *
     * @param Integer $codpes : Número USP
     * @return Array
     * @author @gabrielareisg 31/05/2021
     **/
    public static function obterIntercambioPorCodpes(int $codpes)
    {
        $codundclgi = getenv('REPLICADO_CODUNDCLG');

        $query = DB::getQuery('Graduacao.obterIntercambioPorCodpes.sql');

        $query = str_replace('__codundclgi__', $codundclgi, $query);

        $param = [
            'codpes' => $codpes,
        ];

        $result = DB::fetchAll($query, $param);
        return empty($result) ? '' : $result;
    }

    /**
     * Método que recebe o número USP de um aluno e retorna a sua média ponderada limpa.
     *
     * Se o aluno possuir mais de uma graduação deve passar por parametro o número:
     * sendo 1 referente a primeira graduação/ou única graduação, 2 para a segunda, e assim sucessivamente.
     * Se o parâmetro não for passado, a média a ser retornada será referente ao último curso do aluno.
     *
     * @param Integer $codpes
     * @param Integer $codpgm Código que identifica cada programa do aluno.
     * @return string
     * @author gabrielareisg em 14/06/2021
     */
    public static function obterMediaPonderadaLimpa(int $codpes, int $codpgm = null)
    {
        $query = DB::getQuery('Graduacao.obterMediaPonderadaLimpa.sql');

        if ($codpgm === null) {
            $query_codpgm = "(SELECT MAX(H2.codpgm) FROM HISTESCOLARGR H2 WHERE H2.codpes = convert(int,:codpes))";
        } else {
            $query_codpgm = "convert(int,:codpgm)";
        }

        $query = str_replace('__codpgm__', $query_codpgm, $query);

        $param = [
            'codpes' => $codpes,
            'codpgm' => $codpgm,
        ];

        // recuperando as disciplina cursadas
        $result = DB::fetchAll($query, $param);

        // calculando a media ponderada
        $creditos = 0;
        $soma = 0;
        foreach ($result as $row) {
            $creditos += $row['creaul'] + $row['cretrb'];
            $nota = empty($row['notfim2']) ? $row['notfim'] : $row['notfim2'];
            $mult = $nota * ($row['creaul'] + $row['cretrb']);
            $soma += $mult;
        }
        return empty($soma) ? 0 : round($soma / $creditos, 1);
    }
}
