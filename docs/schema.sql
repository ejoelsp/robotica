--
-- PostgreSQL database dump
--

-- Dumped from database version 17.4
-- Dumped by pg_dump version 17.4

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: auditoria; Type: SCHEMA; Schema: -; Owner: postgres
--

CREATE SCHEMA auditoria;


ALTER SCHEMA auditoria OWNER TO postgres;

--
-- Name: catalogo; Type: SCHEMA; Schema: -; Owner: postgres
--

CREATE SCHEMA catalogo;


ALTER SCHEMA catalogo OWNER TO postgres;

--
-- Name: comunicacion; Type: SCHEMA; Schema: -; Owner: postgres
--

CREATE SCHEMA comunicacion;


ALTER SCHEMA comunicacion OWNER TO postgres;

--
-- Name: resultados; Type: SCHEMA; Schema: -; Owner: postgres
--

CREATE SCHEMA resultados;


ALTER SCHEMA resultados OWNER TO postgres;

--
-- Name: seguridad; Type: SCHEMA; Schema: -; Owner: postgres
--

CREATE SCHEMA seguridad;


ALTER SCHEMA seguridad OWNER TO postgres;

--
-- Name: sistema; Type: SCHEMA; Schema: -; Owner: postgres
--

CREATE SCHEMA sistema;


ALTER SCHEMA sistema OWNER TO postgres;

--
-- Name: vinculaciones; Type: SCHEMA; Schema: -; Owner: postgres
--

CREATE SCHEMA vinculaciones;


ALTER SCHEMA vinculaciones OWNER TO postgres;

--
-- Name: unaccent; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS unaccent WITH SCHEMA sistema;


--
-- Name: EXTENSION unaccent; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION unaccent IS 'text search dictionary that removes accents';


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: auditorias; Type: TABLE; Schema: auditoria; Owner: postgres
--

CREATE TABLE auditoria.auditorias (
    id bigint NOT NULL,
    tabla character varying(120) NOT NULL,
    accion character varying(50) NOT NULL,
    user_id bigint,
    payload jsonb,
    ocurrio_en timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    usuario_id bigint
);


ALTER TABLE auditoria.auditorias OWNER TO postgres;

--
-- Name: auditorias_id_seq; Type: SEQUENCE; Schema: auditoria; Owner: postgres
--

CREATE SEQUENCE auditoria.auditorias_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE auditoria.auditorias_id_seq OWNER TO postgres;

--
-- Name: auditorias_id_seq; Type: SEQUENCE OWNED BY; Schema: auditoria; Owner: postgres
--

ALTER SEQUENCE auditoria.auditorias_id_seq OWNED BY auditoria.auditorias.id;


--
-- Name: categorias; Type: TABLE; Schema: catalogo; Owner: postgres
--

CREATE TABLE catalogo.categorias (
    id bigint NOT NULL,
    competencia_id bigint NOT NULL,
    nombre character varying(150) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    reglamento text,
    estado boolean DEFAULT true NOT NULL,
    nombre_key character varying(200) NOT NULL,
    imagen character varying(255),
    costo_inscripcion numeric(8,2) DEFAULT '0'::numeric NOT NULL,
    CONSTRAINT categorias_reglamento_pdf_chk CHECK (((reglamento IS NULL) OR (reglamento ~~* '%.pdf'::text)))
);


ALTER TABLE catalogo.categorias OWNER TO postgres;

--
-- Name: categorias_id_seq; Type: SEQUENCE; Schema: catalogo; Owner: postgres
--

CREATE SEQUENCE catalogo.categorias_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE catalogo.categorias_id_seq OWNER TO postgres;

--
-- Name: categorias_id_seq; Type: SEQUENCE OWNED BY; Schema: catalogo; Owner: postgres
--

ALTER SEQUENCE catalogo.categorias_id_seq OWNED BY catalogo.categorias.id;


--
-- Name: competencias; Type: TABLE; Schema: catalogo; Owner: postgres
--

CREATE TABLE catalogo.competencias (
    id bigint NOT NULL,
    nombre character varying(200) NOT NULL,
    descripcion text,
    fecha_inicio timestamp(0) without time zone NOT NULL,
    fecha_fin timestamp(0) without time zone NOT NULL,
    enlace_evento character varying(255) DEFAULT 'borrador'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    tipo_competencia character varying(20),
    estado boolean DEFAULT false NOT NULL,
    temporada_id bigint,
    imagen_url character varying(255)
);


ALTER TABLE catalogo.competencias OWNER TO postgres;

--
-- Name: competencias_id_seq; Type: SEQUENCE; Schema: catalogo; Owner: postgres
--

CREATE SEQUENCE catalogo.competencias_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE catalogo.competencias_id_seq OWNER TO postgres;

--
-- Name: competencias_id_seq; Type: SEQUENCE OWNED BY; Schema: catalogo; Owner: postgres
--

ALTER SEQUENCE catalogo.competencias_id_seq OWNED BY catalogo.competencias.id;


--
-- Name: equipos; Type: TABLE; Schema: catalogo; Owner: postgres
--

CREATE TABLE catalogo.equipos (
    id bigint NOT NULL,
    nombre character varying(150) NOT NULL,
    capitan_user_id bigint,
    institucion character varying(150),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE catalogo.equipos OWNER TO postgres;

--
-- Name: equipos_id_seq; Type: SEQUENCE; Schema: catalogo; Owner: postgres
--

CREATE SEQUENCE catalogo.equipos_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE catalogo.equipos_id_seq OWNER TO postgres;

--
-- Name: equipos_id_seq; Type: SEQUENCE OWNED BY; Schema: catalogo; Owner: postgres
--

ALTER SEQUENCE catalogo.equipos_id_seq OWNED BY catalogo.equipos.id;


--
-- Name: rondas; Type: TABLE; Schema: catalogo; Owner: postgres
--

CREATE TABLE catalogo.rondas (
    id bigint NOT NULL,
    categoria_id bigint NOT NULL,
    nombre character varying(100) NOT NULL,
    fecha_hora timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE catalogo.rondas OWNER TO postgres;

--
-- Name: rondas_id_seq; Type: SEQUENCE; Schema: catalogo; Owner: postgres
--

CREATE SEQUENCE catalogo.rondas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE catalogo.rondas_id_seq OWNER TO postgres;

--
-- Name: rondas_id_seq; Type: SEQUENCE OWNED BY; Schema: catalogo; Owner: postgres
--

ALTER SEQUENCE catalogo.rondas_id_seq OWNED BY catalogo.rondas.id;


--
-- Name: temporadas; Type: TABLE; Schema: catalogo; Owner: postgres
--

CREATE TABLE catalogo.temporadas (
    id bigint NOT NULL,
    nombre character varying(150) NOT NULL,
    anio integer NOT NULL
);


ALTER TABLE catalogo.temporadas OWNER TO postgres;

--
-- Name: temporadas_id_seq; Type: SEQUENCE; Schema: catalogo; Owner: postgres
--

CREATE SEQUENCE catalogo.temporadas_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE catalogo.temporadas_id_seq OWNER TO postgres;

--
-- Name: temporadas_id_seq; Type: SEQUENCE OWNED BY; Schema: catalogo; Owner: postgres
--

ALTER SEQUENCE catalogo.temporadas_id_seq OWNED BY catalogo.temporadas.id;


--
-- Name: notificaciones; Type: TABLE; Schema: comunicacion; Owner: postgres
--

CREATE TABLE comunicacion.notificaciones (
    id bigint NOT NULL,
    user_id bigint,
    competencia_id bigint,
    canal character varying(30) DEFAULT 'email'::character varying NOT NULL,
    asunto character varying(200),
    contenido text,
    estado character varying(255) DEFAULT 'pendiente'::character varying NOT NULL,
    reintentos smallint DEFAULT '0'::smallint NOT NULL,
    enviado_en timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT notificaciones_estado_check CHECK (((estado)::text = ANY ((ARRAY['pendiente'::character varying, 'enviado'::character varying, 'error'::character varying])::text[])))
);


ALTER TABLE comunicacion.notificaciones OWNER TO postgres;

--
-- Name: notificaciones_id_seq; Type: SEQUENCE; Schema: comunicacion; Owner: postgres
--

CREATE SEQUENCE comunicacion.notificaciones_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE comunicacion.notificaciones_id_seq OWNER TO postgres;

--
-- Name: notificaciones_id_seq; Type: SEQUENCE OWNED BY; Schema: comunicacion; Owner: postgres
--

ALTER SEQUENCE comunicacion.notificaciones_id_seq OWNED BY comunicacion.notificaciones.id;


--
-- Name: clasificaciones; Type: TABLE; Schema: resultados; Owner: postgres
--

CREATE TABLE resultados.clasificaciones (
    id bigint NOT NULL,
    categoria_id bigint NOT NULL,
    equipo_id bigint NOT NULL,
    puntaje_total numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    tiempo_total numeric(12,3),
    penal_total integer DEFAULT 0 NOT NULL,
    posicion integer,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE resultados.clasificaciones OWNER TO postgres;

--
-- Name: clasificaciones_id_seq; Type: SEQUENCE; Schema: resultados; Owner: postgres
--

CREATE SEQUENCE resultados.clasificaciones_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE resultados.clasificaciones_id_seq OWNER TO postgres;

--
-- Name: clasificaciones_id_seq; Type: SEQUENCE OWNED BY; Schema: resultados; Owner: postgres
--

ALTER SEQUENCE resultados.clasificaciones_id_seq OWNED BY resultados.clasificaciones.id;


--
-- Name: incidencias; Type: TABLE; Schema: resultados; Owner: postgres
--

CREATE TABLE resultados.incidencias (
    id bigint NOT NULL,
    categoria_id bigint,
    equipo_id bigint,
    reportado_por bigint,
    tipo character varying(255) DEFAULT 'observacion'::character varying NOT NULL,
    descripcion text,
    evidencia_path character varying(500),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT incidencias_tipo_check CHECK (((tipo)::text = ANY ((ARRAY['observacion'::character varying, 'penalizacion'::character varying])::text[])))
);


ALTER TABLE resultados.incidencias OWNER TO postgres;

--
-- Name: incidencias_id_seq; Type: SEQUENCE; Schema: resultados; Owner: postgres
--

CREATE SEQUENCE resultados.incidencias_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE resultados.incidencias_id_seq OWNER TO postgres;

--
-- Name: incidencias_id_seq; Type: SEQUENCE OWNED BY; Schema: resultados; Owner: postgres
--

ALTER SEQUENCE resultados.incidencias_id_seq OWNED BY resultados.incidencias.id;


--
-- Name: resultados; Type: TABLE; Schema: resultados; Owner: postgres
--

CREATE TABLE resultados.resultados (
    id bigint NOT NULL,
    ronda_id bigint NOT NULL,
    equipo_id bigint NOT NULL,
    juez_user_id bigint NOT NULL,
    puntaje numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    tiempo numeric(10,3),
    penalizaciones integer DEFAULT 0 NOT NULL,
    version integer DEFAULT 1 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE resultados.resultados OWNER TO postgres;

--
-- Name: resultados_hist; Type: TABLE; Schema: resultados; Owner: postgres
--

CREATE TABLE resultados.resultados_hist (
    id bigint NOT NULL,
    resultado_id bigint NOT NULL,
    version integer NOT NULL,
    puntaje_old numeric(10,2),
    puntaje_new numeric(10,2),
    tiempo_old numeric(10,3),
    tiempo_new numeric(10,3),
    penal_old integer,
    penal_new integer,
    editado_por bigint,
    editado_en timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE resultados.resultados_hist OWNER TO postgres;

--
-- Name: resultados_hist_id_seq; Type: SEQUENCE; Schema: resultados; Owner: postgres
--

CREATE SEQUENCE resultados.resultados_hist_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE resultados.resultados_hist_id_seq OWNER TO postgres;

--
-- Name: resultados_hist_id_seq; Type: SEQUENCE OWNED BY; Schema: resultados; Owner: postgres
--

ALTER SEQUENCE resultados.resultados_hist_id_seq OWNED BY resultados.resultados_hist.id;


--
-- Name: resultados_id_seq; Type: SEQUENCE; Schema: resultados; Owner: postgres
--

CREATE SEQUENCE resultados.resultados_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE resultados.resultados_id_seq OWNER TO postgres;

--
-- Name: resultados_id_seq; Type: SEQUENCE OWNED BY; Schema: resultados; Owner: postgres
--

ALTER SEQUENCE resultados.resultados_id_seq OWNED BY resultados.resultados.id;


--
-- Name: roles; Type: TABLE; Schema: seguridad; Owner: postgres
--

CREATE TABLE seguridad.roles (
    id bigint NOT NULL,
    nombre character varying(50) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE seguridad.roles OWNER TO postgres;

--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: seguridad; Owner: postgres
--

CREATE SEQUENCE seguridad.roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE seguridad.roles_id_seq OWNER TO postgres;

--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: seguridad; Owner: postgres
--

ALTER SEQUENCE seguridad.roles_id_seq OWNED BY seguridad.roles.id;


--
-- Name: sessions; Type: TABLE; Schema: seguridad; Owner: postgres
--

CREATE TABLE seguridad.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


ALTER TABLE seguridad.sessions OWNER TO postgres;

--
-- Name: user_activation_tokens; Type: TABLE; Schema: seguridad; Owner: postgres
--

CREATE TABLE seguridad.user_activation_tokens (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    token character varying(128) NOT NULL,
    expires_at timestamp(0) without time zone NOT NULL,
    used_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE seguridad.user_activation_tokens OWNER TO postgres;

--
-- Name: user_activation_tokens_id_seq; Type: SEQUENCE; Schema: seguridad; Owner: postgres
--

CREATE SEQUENCE seguridad.user_activation_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE seguridad.user_activation_tokens_id_seq OWNER TO postgres;

--
-- Name: user_activation_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: seguridad; Owner: postgres
--

ALTER SEQUENCE seguridad.user_activation_tokens_id_seq OWNED BY seguridad.user_activation_tokens.id;


--
-- Name: users; Type: TABLE; Schema: seguridad; Owner: postgres
--

CREATE TABLE seguridad.users (
    id bigint NOT NULL,
    name character varying(30) NOT NULL,
    email character varying(100) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    role_id bigint NOT NULL,
    telefono character varying(20),
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    last_name character varying(30) NOT NULL,
    must_change_password boolean DEFAULT false NOT NULL,
    photo_path character varying(255)
);


ALTER TABLE seguridad.users OWNER TO postgres;

--
-- Name: users_id_seq; Type: SEQUENCE; Schema: seguridad; Owner: postgres
--

CREATE SEQUENCE seguridad.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE seguridad.users_id_seq OWNER TO postgres;

--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: seguridad; Owner: postgres
--

ALTER SEQUENCE seguridad.users_id_seq OWNED BY seguridad.users.id;


--
-- Name: backups_log; Type: TABLE; Schema: sistema; Owner: postgres
--

CREATE TABLE sistema.backups_log (
    id bigint NOT NULL,
    archivo character varying(300) NOT NULL,
    hash character varying(128),
    bytes bigint,
    estado character varying(30) DEFAULT 'creado'::character varying NOT NULL,
    creado_en timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE sistema.backups_log OWNER TO postgres;

--
-- Name: backups_log_id_seq; Type: SEQUENCE; Schema: sistema; Owner: postgres
--

CREATE SEQUENCE sistema.backups_log_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE sistema.backups_log_id_seq OWNER TO postgres;

--
-- Name: backups_log_id_seq; Type: SEQUENCE OWNED BY; Schema: sistema; Owner: postgres
--

ALTER SEQUENCE sistema.backups_log_id_seq OWNED BY sistema.backups_log.id;


--
-- Name: cache; Type: TABLE; Schema: sistema; Owner: postgres
--

CREATE TABLE sistema.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


ALTER TABLE sistema.cache OWNER TO postgres;

--
-- Name: cache_locks; Type: TABLE; Schema: sistema; Owner: postgres
--

CREATE TABLE sistema.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


ALTER TABLE sistema.cache_locks OWNER TO postgres;

--
-- Name: failed_jobs; Type: TABLE; Schema: sistema; Owner: postgres
--

CREATE TABLE sistema.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE sistema.failed_jobs OWNER TO postgres;

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: sistema; Owner: postgres
--

CREATE SEQUENCE sistema.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE sistema.failed_jobs_id_seq OWNER TO postgres;

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: sistema; Owner: postgres
--

ALTER SEQUENCE sistema.failed_jobs_id_seq OWNED BY sistema.failed_jobs.id;


--
-- Name: job_batches; Type: TABLE; Schema: sistema; Owner: postgres
--

CREATE TABLE sistema.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


ALTER TABLE sistema.job_batches OWNER TO postgres;

--
-- Name: jobs; Type: TABLE; Schema: sistema; Owner: postgres
--

CREATE TABLE sistema.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


ALTER TABLE sistema.jobs OWNER TO postgres;

--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: sistema; Owner: postgres
--

CREATE SEQUENCE sistema.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE sistema.jobs_id_seq OWNER TO postgres;

--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: sistema; Owner: postgres
--

ALTER SEQUENCE sistema.jobs_id_seq OWNED BY sistema.jobs.id;


--
-- Name: jobs_log; Type: TABLE; Schema: sistema; Owner: postgres
--

CREATE TABLE sistema.jobs_log (
    id bigint NOT NULL,
    job character varying(120) NOT NULL,
    estado character varying(30) NOT NULL,
    duracion_ms integer,
    detalle text,
    ejecutado_en timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE sistema.jobs_log OWNER TO postgres;

--
-- Name: jobs_log_id_seq; Type: SEQUENCE; Schema: sistema; Owner: postgres
--

CREATE SEQUENCE sistema.jobs_log_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE sistema.jobs_log_id_seq OWNER TO postgres;

--
-- Name: jobs_log_id_seq; Type: SEQUENCE OWNED BY; Schema: sistema; Owner: postgres
--

ALTER SEQUENCE sistema.jobs_log_id_seq OWNED BY sistema.jobs_log.id;


--
-- Name: migrations; Type: TABLE; Schema: sistema; Owner: postgres
--

CREATE TABLE sistema.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


ALTER TABLE sistema.migrations OWNER TO postgres;

--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: sistema; Owner: postgres
--

CREATE SEQUENCE sistema.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE sistema.migrations_id_seq OWNER TO postgres;

--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: sistema; Owner: postgres
--

ALTER SEQUENCE sistema.migrations_id_seq OWNED BY sistema.migrations.id;


--
-- Name: asignaciones_juez_categoria; Type: TABLE; Schema: vinculaciones; Owner: postgres
--

CREATE TABLE vinculaciones.asignaciones_juez_categoria (
    id bigint NOT NULL,
    categoria_id bigint NOT NULL,
    juez_user_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    rol character varying(20) DEFAULT 'principal'::character varying NOT NULL,
    CONSTRAINT asignaciones_juez_categoria_rol_check CHECK (((rol)::text = ANY ((ARRAY['principal'::character varying, 'apoyo'::character varying])::text[])))
);


ALTER TABLE vinculaciones.asignaciones_juez_categoria OWNER TO postgres;

--
-- Name: asignaciones_juez_categoria_id_seq; Type: SEQUENCE; Schema: vinculaciones; Owner: postgres
--

CREATE SEQUENCE vinculaciones.asignaciones_juez_categoria_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE vinculaciones.asignaciones_juez_categoria_id_seq OWNER TO postgres;

--
-- Name: asignaciones_juez_categoria_id_seq; Type: SEQUENCE OWNED BY; Schema: vinculaciones; Owner: postgres
--

ALTER SEQUENCE vinculaciones.asignaciones_juez_categoria_id_seq OWNED BY vinculaciones.asignaciones_juez_categoria.id;


--
-- Name: inscripcion_integrantes; Type: TABLE; Schema: vinculaciones; Owner: postgres
--

CREATE TABLE vinculaciones.inscripcion_integrantes (
    id bigint NOT NULL,
    inscripcion_id bigint NOT NULL,
    nombre_completo character varying(255) NOT NULL,
    user_id bigint,
    es_capitan boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE vinculaciones.inscripcion_integrantes OWNER TO postgres;

--
-- Name: inscripcion_integrantes_id_seq; Type: SEQUENCE; Schema: vinculaciones; Owner: postgres
--

CREATE SEQUENCE vinculaciones.inscripcion_integrantes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE vinculaciones.inscripcion_integrantes_id_seq OWNER TO postgres;

--
-- Name: inscripcion_integrantes_id_seq; Type: SEQUENCE OWNED BY; Schema: vinculaciones; Owner: postgres
--

ALTER SEQUENCE vinculaciones.inscripcion_integrantes_id_seq OWNED BY vinculaciones.inscripcion_integrantes.id;


--
-- Name: inscripciones; Type: TABLE; Schema: vinculaciones; Owner: postgres
--

CREATE TABLE vinculaciones.inscripciones (
    id bigint NOT NULL,
    competencia_id bigint NOT NULL,
    equipo_id bigint NOT NULL,
    user_id bigint,
    codigo character varying(40) NOT NULL,
    estado character varying(255) DEFAULT 'pendiente'::character varying NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    categoria_id bigint,
    nombre_prototipo character varying(100),
    telefono_contacto character varying(20),
    comprobante_pago character varying(255),
    fecha_subida_comprobante timestamp without time zone,
    estado_comprobante character varying(30) DEFAULT 'no_subido'::character varying NOT NULL,
    motivo_rechazo character varying(150),
    observacion_rechazo text,
    fecha_revision_comprobante timestamp(0) without time zone,
    revisado_por bigint,
    CONSTRAINT inscripciones_estado_check CHECK (((estado)::text = ANY ((ARRAY['pendiente_pago'::character varying, 'revision'::character varying, 'confirmado'::character varying])::text[]))),
    CONSTRAINT inscripciones_estado_comprobante_check CHECK (((estado_comprobante)::text = ANY ((ARRAY['no_subido'::character varying, 'revision'::character varying, 'aprobado'::character varying, 'rechazado'::character varying])::text[])))
);


ALTER TABLE vinculaciones.inscripciones OWNER TO postgres;

--
-- Name: inscripciones_id_seq; Type: SEQUENCE; Schema: vinculaciones; Owner: postgres
--

CREATE SEQUENCE vinculaciones.inscripciones_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE vinculaciones.inscripciones_id_seq OWNER TO postgres;

--
-- Name: inscripciones_id_seq; Type: SEQUENCE OWNED BY; Schema: vinculaciones; Owner: postgres
--

ALTER SEQUENCE vinculaciones.inscripciones_id_seq OWNED BY vinculaciones.inscripciones.id;


--
-- Name: auditorias id; Type: DEFAULT; Schema: auditoria; Owner: postgres
--

ALTER TABLE ONLY auditoria.auditorias ALTER COLUMN id SET DEFAULT nextval('auditoria.auditorias_id_seq'::regclass);


--
-- Name: categorias id; Type: DEFAULT; Schema: catalogo; Owner: postgres
--

ALTER TABLE ONLY catalogo.categorias ALTER COLUMN id SET DEFAULT nextval('catalogo.categorias_id_seq'::regclass);


--
-- Name: competencias id; Type: DEFAULT; Schema: catalogo; Owner: postgres
--

ALTER TABLE ONLY catalogo.competencias ALTER COLUMN id SET DEFAULT nextval('catalogo.competencias_id_seq'::regclass);


--
-- Name: equipos id; Type: DEFAULT; Schema: catalogo; Owner: postgres
--

ALTER TABLE ONLY catalogo.equipos ALTER COLUMN id SET DEFAULT nextval('catalogo.equipos_id_seq'::regclass);


--
-- Name: rondas id; Type: DEFAULT; Schema: catalogo; Owner: postgres
--

ALTER TABLE ONLY catalogo.rondas ALTER COLUMN id SET DEFAULT nextval('catalogo.rondas_id_seq'::regclass);


--
-- Name: temporadas id; Type: DEFAULT; Schema: catalogo; Owner: postgres
--

ALTER TABLE ONLY catalogo.temporadas ALTER COLUMN id SET DEFAULT nextval('catalogo.temporadas_id_seq'::regclass);


--
-- Name: notificaciones id; Type: DEFAULT; Schema: comunicacion; Owner: postgres
--

ALTER TABLE ONLY comunicacion.notificaciones ALTER COLUMN id SET DEFAULT nextval('comunicacion.notificaciones_id_seq'::regclass);


--
-- Name: clasificaciones id; Type: DEFAULT; Schema: resultados; Owner: postgres
--

ALTER TABLE ONLY resultados.clasificaciones ALTER COLUMN id SET DEFAULT nextval('resultados.clasificaciones_id_seq'::regclass);


--
-- Name: incidencias id; Type: DEFAULT; Schema: resultados; Owner: postgres
--

ALTER TABLE ONLY resultados.incidencias ALTER COLUMN id SET DEFAULT nextval('resultados.incidencias_id_seq'::regclass);


--
-- Name: resultados id; Type: DEFAULT; Schema: resultados; Owner: postgres
--

ALTER TABLE ONLY resultados.resultados ALTER COLUMN id SET DEFAULT nextval('resultados.resultados_id_seq'::regclass);


--
-- Name: resultados_hist id; Type: DEFAULT; Schema: resultados; Owner: postgres
--

ALTER TABLE ONLY resultados.resultados_hist ALTER COLUMN id SET DEFAULT nextval('resultados.resultados_hist_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: seguridad; Owner: postgres
--

ALTER TABLE ONLY seguridad.roles ALTER COLUMN id SET DEFAULT nextval('seguridad.roles_id_seq'::regclass);


--
-- Name: user_activation_tokens id; Type: DEFAULT; Schema: seguridad; Owner: postgres
--

ALTER TABLE ONLY seguridad.user_activation_tokens ALTER COLUMN id SET DEFAULT nextval('seguridad.user_activation_tokens_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: seguridad; Owner: postgres
--

ALTER TABLE ONLY seguridad.users ALTER COLUMN id SET DEFAULT nextval('seguridad.users_id_seq'::regclass);


--
-- Name: backups_log id; Type: DEFAULT; Schema: sistema; Owner: postgres
--

ALTER TABLE ONLY sistema.backups_log ALTER COLUMN id SET DEFAULT nextval('sistema.backups_log_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: sistema; Owner: postgres
--

ALTER TABLE ONLY sistema.failed_jobs ALTER COLUMN id SET DEFAULT nextval('sistema.failed_jobs_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: sistema; Owner: postgres
--

ALTER TABLE ONLY sistema.jobs ALTER COLUMN id SET DEFAULT nextval('sistema.jobs_id_seq'::regclass);


--
-- Name: jobs_log id; Type: DEFAULT; Schema: sistema; Owner: postgres
--

ALTER TABLE ONLY sistema.jobs_log ALTER COLUMN id SET DEFAULT nextval('sistema.jobs_log_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: sistema; Owner: postgres
--

ALTER TABLE ONLY sistema.migrations ALTER COLUMN id SET DEFAULT nextval('sistema.migrations_id_seq'::regclass);


--
-- Name: asignaciones_juez_categoria id; Type: DEFAULT; Schema: vinculaciones; Owner: postgres
--

ALTER TABLE ONLY vinculaciones.asignaciones_juez_categoria ALTER COLUMN id SET DEFAULT nextval('vinculaciones.asignaciones_juez_categoria_id_seq'::regclass);


--
-- Name: inscripcion_integrantes id; Type: DEFAULT; Schema: vinculaciones; Owner: postgres
--

ALTER TABLE ONLY vinculaciones.inscripcion_integrantes ALTER COLUMN id SET DEFAULT nextval('vinculaciones.inscripcion_integrantes_id_seq'::regclass);


--
-- Name: inscripciones id; Type: DEFAULT; Schema: vinculaciones; Owner: postgres
--

ALTER TABLE ONLY vinculaciones.inscripciones ALTER COLUMN id SET DEFAULT nextval('vinculaciones.inscripciones_id_seq'::regclass);


--
-- Name: inscripciones codigo; Type: DEFAULT; Schema: vinculaciones; Owner: postgres
--

ALTER TABLE ONLY vinculaciones.inscripciones ALTER COLUMN codigo SET DEFAULT concat('INS-', lpad((nextval('vinculaciones.inscripciones_id_seq'::regclass))::text, 4, '0'::text));


--
-- Name: auditorias auditorias_pkey; Type: CONSTRAINT; Schema: auditoria; Owner: postgres
--

ALTER TABLE ONLY auditoria.auditorias
    ADD CONSTRAINT auditorias_pkey PRIMARY KEY (id);


--
-- Name: categorias categorias_competencia_id_nombre_key_unique; Type: CONSTRAINT; Schema: catalogo; Owner: postgres
--

ALTER TABLE ONLY catalogo.categorias
    ADD CONSTRAINT categorias_competencia_id_nombre_key_unique UNIQUE (competencia_id, nombre_key);


--
-- Name: categorias categorias_competencia_id_nombre_unique; Type: CONSTRAINT; Schema: catalogo; Owner: postgres
--

ALTER TABLE ONLY catalogo.categorias
    ADD CONSTRAINT categorias_competencia_id_nombre_unique UNIQUE (competencia_id, nombre);


--
-- Name: categorias categorias_pkey; Type: CONSTRAINT; Schema: catalogo; Owner: postgres
--

ALTER TABLE ONLY catalogo.categorias
    ADD CONSTRAINT categorias_pkey PRIMARY KEY (id);


--
-- Name: competencias competencias_nombre_unique; Type: CONSTRAINT; Schema: catalogo; Owner: postgres
--

ALTER TABLE ONLY catalogo.competencias
    ADD CONSTRAINT competencias_nombre_unique UNIQUE (nombre);


--
-- Name: competencias competencias_pkey; Type: CONSTRAINT; Schema: catalogo; Owner: postgres
--

ALTER TABLE ONLY catalogo.competencias
    ADD CONSTRAINT competencias_pkey PRIMARY KEY (id);


--
-- Name: equipos equipos_nombre_institucion_unique; Type: CONSTRAINT; Schema: catalogo; Owner: postgres
--

ALTER TABLE ONLY catalogo.equipos
    ADD CONSTRAINT equipos_nombre_institucion_unique UNIQUE (nombre, institucion);


--
-- Name: equipos equipos_pkey; Type: CONSTRAINT; Schema: catalogo; Owner: postgres
--

ALTER TABLE ONLY catalogo.equipos
    ADD CONSTRAINT equipos_pkey PRIMARY KEY (id);


--
-- Name: rondas rondas_categoria_id_nombre_unique; Type: CONSTRAINT; Schema: catalogo; Owner: postgres
--

ALTER TABLE ONLY catalogo.rondas
    ADD CONSTRAINT rondas_categoria_id_nombre_unique UNIQUE (categoria_id, nombre);


--
-- Name: rondas rondas_pkey; Type: CONSTRAINT; Schema: catalogo; Owner: postgres
--

ALTER TABLE ONLY catalogo.rondas
    ADD CONSTRAINT rondas_pkey PRIMARY KEY (id);


--
-- Name: temporadas temporadas_pkey; Type: CONSTRAINT; Schema: catalogo; Owner: postgres
--

ALTER TABLE ONLY catalogo.temporadas
    ADD CONSTRAINT temporadas_pkey PRIMARY KEY (id);


--
-- Name: notificaciones notificaciones_pkey; Type: CONSTRAINT; Schema: comunicacion; Owner: postgres
--

ALTER TABLE ONLY comunicacion.notificaciones
    ADD CONSTRAINT notificaciones_pkey PRIMARY KEY (id);


--
-- Name: clasificaciones clasificaciones_categoria_id_equipo_id_unique; Type: CONSTRAINT; Schema: resultados; Owner: postgres
--

ALTER TABLE ONLY resultados.clasificaciones
    ADD CONSTRAINT clasificaciones_categoria_id_equipo_id_unique UNIQUE (categoria_id, equipo_id);


--
-- Name: clasificaciones clasificaciones_pkey; Type: CONSTRAINT; Schema: resultados; Owner: postgres
--

ALTER TABLE ONLY resultados.clasificaciones
    ADD CONSTRAINT clasificaciones_pkey PRIMARY KEY (id);


--
-- Name: incidencias incidencias_pkey; Type: CONSTRAINT; Schema: resultados; Owner: postgres
--

ALTER TABLE ONLY resultados.incidencias
    ADD CONSTRAINT incidencias_pkey PRIMARY KEY (id);


--
-- Name: resultados_hist resultados_hist_pkey; Type: CONSTRAINT; Schema: resultados; Owner: postgres
--

ALTER TABLE ONLY resultados.resultados_hist
    ADD CONSTRAINT resultados_hist_pkey PRIMARY KEY (id);


--
-- Name: resultados resultados_pkey; Type: CONSTRAINT; Schema: resultados; Owner: postgres
--

ALTER TABLE ONLY resultados.resultados
    ADD CONSTRAINT resultados_pkey PRIMARY KEY (id);


--
-- Name: resultados resultados_ronda_id_equipo_id_unique; Type: CONSTRAINT; Schema: resultados; Owner: postgres
--

ALTER TABLE ONLY resultados.resultados
    ADD CONSTRAINT resultados_ronda_id_equipo_id_unique UNIQUE (ronda_id, equipo_id);


--
-- Name: roles roles_nombre_unique; Type: CONSTRAINT; Schema: seguridad; Owner: postgres
--

ALTER TABLE ONLY seguridad.roles
    ADD CONSTRAINT roles_nombre_unique UNIQUE (nombre);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: seguridad; Owner: postgres
--

ALTER TABLE ONLY seguridad.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: user_activation_tokens seguridad_user_activation_tokens_token_unique; Type: CONSTRAINT; Schema: seguridad; Owner: postgres
--

ALTER TABLE ONLY seguridad.user_activation_tokens
    ADD CONSTRAINT seguridad_user_activation_tokens_token_unique UNIQUE (token);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: seguridad; Owner: postgres
--

ALTER TABLE ONLY seguridad.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: user_activation_tokens user_activation_tokens_pkey; Type: CONSTRAINT; Schema: seguridad; Owner: postgres
--

ALTER TABLE ONLY seguridad.user_activation_tokens
    ADD CONSTRAINT user_activation_tokens_pkey PRIMARY KEY (id);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: seguridad; Owner: postgres
--

ALTER TABLE ONLY seguridad.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: seguridad; Owner: postgres
--

ALTER TABLE ONLY seguridad.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: backups_log backups_log_archivo_unique; Type: CONSTRAINT; Schema: sistema; Owner: postgres
--

ALTER TABLE ONLY sistema.backups_log
    ADD CONSTRAINT backups_log_archivo_unique UNIQUE (archivo);


--
-- Name: backups_log backups_log_pkey; Type: CONSTRAINT; Schema: sistema; Owner: postgres
--

ALTER TABLE ONLY sistema.backups_log
    ADD CONSTRAINT backups_log_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: sistema; Owner: postgres
--

ALTER TABLE ONLY sistema.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: sistema; Owner: postgres
--

ALTER TABLE ONLY sistema.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: sistema; Owner: postgres
--

ALTER TABLE ONLY sistema.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: sistema; Owner: postgres
--

ALTER TABLE ONLY sistema.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: sistema; Owner: postgres
--

ALTER TABLE ONLY sistema.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs_log jobs_log_pkey; Type: CONSTRAINT; Schema: sistema; Owner: postgres
--

ALTER TABLE ONLY sistema.jobs_log
    ADD CONSTRAINT jobs_log_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: sistema; Owner: postgres
--

ALTER TABLE ONLY sistema.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: sistema; Owner: postgres
--

ALTER TABLE ONLY sistema.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: asignaciones_juez_categoria asignaciones_juez_categoria_categoria_id_juez_user_id_unique; Type: CONSTRAINT; Schema: vinculaciones; Owner: postgres
--

ALTER TABLE ONLY vinculaciones.asignaciones_juez_categoria
    ADD CONSTRAINT asignaciones_juez_categoria_categoria_id_juez_user_id_unique UNIQUE (categoria_id, juez_user_id);


--
-- Name: asignaciones_juez_categoria asignaciones_juez_categoria_pkey; Type: CONSTRAINT; Schema: vinculaciones; Owner: postgres
--

ALTER TABLE ONLY vinculaciones.asignaciones_juez_categoria
    ADD CONSTRAINT asignaciones_juez_categoria_pkey PRIMARY KEY (id);


--
-- Name: inscripcion_integrantes inscripcion_integrantes_pkey; Type: CONSTRAINT; Schema: vinculaciones; Owner: postgres
--

ALTER TABLE ONLY vinculaciones.inscripcion_integrantes
    ADD CONSTRAINT inscripcion_integrantes_pkey PRIMARY KEY (id);


--
-- Name: inscripciones inscripciones_codigo_unique; Type: CONSTRAINT; Schema: vinculaciones; Owner: postgres
--

ALTER TABLE ONLY vinculaciones.inscripciones
    ADD CONSTRAINT inscripciones_codigo_unique UNIQUE (codigo);


--
-- Name: inscripciones inscripciones_competencia_categoria_equipo_unique; Type: CONSTRAINT; Schema: vinculaciones; Owner: postgres
--

ALTER TABLE ONLY vinculaciones.inscripciones
    ADD CONSTRAINT inscripciones_competencia_categoria_equipo_unique UNIQUE (competencia_id, categoria_id, equipo_id);


--
-- Name: inscripciones inscripciones_pkey; Type: CONSTRAINT; Schema: vinculaciones; Owner: postgres
--

ALTER TABLE ONLY vinculaciones.inscripciones
    ADD CONSTRAINT inscripciones_pkey PRIMARY KEY (id);


--
-- Name: auditorias_tabla_accion_index; Type: INDEX; Schema: auditoria; Owner: postgres
--

CREATE INDEX auditorias_tabla_accion_index ON auditoria.auditorias USING btree (tabla, accion);


--
-- Name: auditorias_user_id_index; Type: INDEX; Schema: auditoria; Owner: postgres
--

CREATE INDEX auditorias_user_id_index ON auditoria.auditorias USING btree (user_id);


--
-- Name: categorias_competencia_id_index; Type: INDEX; Schema: catalogo; Owner: postgres
--

CREATE INDEX categorias_competencia_id_index ON catalogo.categorias USING btree (competencia_id);


--
-- Name: categorias_competencia_nombre_key_unique; Type: INDEX; Schema: catalogo; Owner: postgres
--

CREATE UNIQUE INDEX categorias_competencia_nombre_key_unique ON catalogo.categorias USING btree (competencia_id, nombre_key);


--
-- Name: competencias_estado_fecha_inicio_index; Type: INDEX; Schema: catalogo; Owner: postgres
--

CREATE INDEX competencias_estado_fecha_inicio_index ON catalogo.competencias USING btree (enlace_evento, fecha_inicio);


--
-- Name: equipos_capitan_user_id_index; Type: INDEX; Schema: catalogo; Owner: postgres
--

CREATE INDEX equipos_capitan_user_id_index ON catalogo.equipos USING btree (capitan_user_id);


--
-- Name: rondas_categoria_id_index; Type: INDEX; Schema: catalogo; Owner: postgres
--

CREATE INDEX rondas_categoria_id_index ON catalogo.rondas USING btree (categoria_id);


--
-- Name: notificaciones_competencia_id_index; Type: INDEX; Schema: comunicacion; Owner: postgres
--

CREATE INDEX notificaciones_competencia_id_index ON comunicacion.notificaciones USING btree (competencia_id);


--
-- Name: notificaciones_estado_competencia_id_index; Type: INDEX; Schema: comunicacion; Owner: postgres
--

CREATE INDEX notificaciones_estado_competencia_id_index ON comunicacion.notificaciones USING btree (estado, competencia_id);


--
-- Name: notificaciones_user_id_index; Type: INDEX; Schema: comunicacion; Owner: postgres
--

CREATE INDEX notificaciones_user_id_index ON comunicacion.notificaciones USING btree (user_id);


--
-- Name: clasificaciones_categoria_id_index; Type: INDEX; Schema: resultados; Owner: postgres
--

CREATE INDEX clasificaciones_categoria_id_index ON resultados.clasificaciones USING btree (categoria_id);


--
-- Name: clasificaciones_categoria_id_posicion_index; Type: INDEX; Schema: resultados; Owner: postgres
--

CREATE INDEX clasificaciones_categoria_id_posicion_index ON resultados.clasificaciones USING btree (categoria_id, posicion);


--
-- Name: clasificaciones_equipo_id_index; Type: INDEX; Schema: resultados; Owner: postgres
--

CREATE INDEX clasificaciones_equipo_id_index ON resultados.clasificaciones USING btree (equipo_id);


--
-- Name: clasificaciones_posicion_index; Type: INDEX; Schema: resultados; Owner: postgres
--

CREATE INDEX clasificaciones_posicion_index ON resultados.clasificaciones USING btree (posicion);


--
-- Name: incidencias_categoria_id_index; Type: INDEX; Schema: resultados; Owner: postgres
--

CREATE INDEX incidencias_categoria_id_index ON resultados.incidencias USING btree (categoria_id);


--
-- Name: incidencias_equipo_id_index; Type: INDEX; Schema: resultados; Owner: postgres
--

CREATE INDEX incidencias_equipo_id_index ON resultados.incidencias USING btree (equipo_id);


--
-- Name: incidencias_reportado_por_index; Type: INDEX; Schema: resultados; Owner: postgres
--

CREATE INDEX incidencias_reportado_por_index ON resultados.incidencias USING btree (reportado_por);


--
-- Name: resultados_equipo_id_index; Type: INDEX; Schema: resultados; Owner: postgres
--

CREATE INDEX resultados_equipo_id_index ON resultados.resultados USING btree (equipo_id);


--
-- Name: resultados_hist_editado_por_index; Type: INDEX; Schema: resultados; Owner: postgres
--

CREATE INDEX resultados_hist_editado_por_index ON resultados.resultados_hist USING btree (editado_por);


--
-- Name: resultados_hist_resultado_id_index; Type: INDEX; Schema: resultados; Owner: postgres
--

CREATE INDEX resultados_hist_resultado_id_index ON resultados.resultados_hist USING btree (resultado_id);


--
-- Name: resultados_juez_user_id_index; Type: INDEX; Schema: resultados; Owner: postgres
--

CREATE INDEX resultados_juez_user_id_index ON resultados.resultados USING btree (juez_user_id);


--
-- Name: resultados_ronda_id_index; Type: INDEX; Schema: resultados; Owner: postgres
--

CREATE INDEX resultados_ronda_id_index ON resultados.resultados USING btree (ronda_id);


--
-- Name: resultados_ronda_id_puntaje_index; Type: INDEX; Schema: resultados; Owner: postgres
--

CREATE INDEX resultados_ronda_id_puntaje_index ON resultados.resultados USING btree (ronda_id, puntaje);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: seguridad; Owner: postgres
--

CREATE INDEX sessions_last_activity_index ON seguridad.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_last_activity_index; Type: INDEX; Schema: seguridad; Owner: postgres
--

CREATE INDEX sessions_user_id_last_activity_index ON seguridad.sessions USING btree (user_id, last_activity);


--
-- Name: users_role_id_index; Type: INDEX; Schema: seguridad; Owner: postgres
--

CREATE INDEX users_role_id_index ON seguridad.users USING btree (role_id);


--
-- Name: backups_log_estado_creado_en_index; Type: INDEX; Schema: sistema; Owner: postgres
--

CREATE INDEX backups_log_estado_creado_en_index ON sistema.backups_log USING btree (estado, creado_en);


--
-- Name: jobs_log_job_estado_ejecutado_en_index; Type: INDEX; Schema: sistema; Owner: postgres
--

CREATE INDEX jobs_log_job_estado_ejecutado_en_index ON sistema.jobs_log USING btree (job, estado, ejecutado_en);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: sistema; Owner: postgres
--

CREATE INDEX jobs_queue_index ON sistema.jobs USING btree (queue);


--
-- Name: asignaciones_juez_categoria_categoria_id_index; Type: INDEX; Schema: vinculaciones; Owner: postgres
--

CREATE INDEX asignaciones_juez_categoria_categoria_id_index ON vinculaciones.asignaciones_juez_categoria USING btree (categoria_id);


--
-- Name: asignaciones_juez_categoria_juez_user_id_index; Type: INDEX; Schema: vinculaciones; Owner: postgres
--

CREATE INDEX asignaciones_juez_categoria_juez_user_id_index ON vinculaciones.asignaciones_juez_categoria USING btree (juez_user_id);


--
-- Name: inscripcion_integrantes_inscripcion_id_index; Type: INDEX; Schema: vinculaciones; Owner: postgres
--

CREATE INDEX inscripcion_integrantes_inscripcion_id_index ON vinculaciones.inscripcion_integrantes USING btree (inscripcion_id);


--
-- Name: inscripcion_integrantes_user_id_index; Type: INDEX; Schema: vinculaciones; Owner: postgres
--

CREATE INDEX inscripcion_integrantes_user_id_index ON vinculaciones.inscripcion_integrantes USING btree (user_id);


--
-- Name: inscripciones_categoria_id_index; Type: INDEX; Schema: vinculaciones; Owner: postgres
--

CREATE INDEX inscripciones_categoria_id_index ON vinculaciones.inscripciones USING btree (categoria_id);


--
-- Name: inscripciones_competencia_id_index; Type: INDEX; Schema: vinculaciones; Owner: postgres
--

CREATE INDEX inscripciones_competencia_id_index ON vinculaciones.inscripciones USING btree (competencia_id);


--
-- Name: inscripciones_equipo_id_index; Type: INDEX; Schema: vinculaciones; Owner: postgres
--

CREATE INDEX inscripciones_equipo_id_index ON vinculaciones.inscripciones USING btree (equipo_id);


--
-- Name: inscripciones_user_id_index; Type: INDEX; Schema: vinculaciones; Owner: postgres
--

CREATE INDEX inscripciones_user_id_index ON vinculaciones.inscripciones USING btree (user_id);


--
-- Name: auditorias auditorias_user_id_foreign; Type: FK CONSTRAINT; Schema: auditoria; Owner: postgres
--

ALTER TABLE ONLY auditoria.auditorias
    ADD CONSTRAINT auditorias_user_id_foreign FOREIGN KEY (user_id) REFERENCES seguridad.users(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: categorias categorias_competencia_id_foreign; Type: FK CONSTRAINT; Schema: catalogo; Owner: postgres
--

ALTER TABLE ONLY catalogo.categorias
    ADD CONSTRAINT categorias_competencia_id_foreign FOREIGN KEY (competencia_id) REFERENCES catalogo.competencias(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: competencias competencias_temporada_id_foreign; Type: FK CONSTRAINT; Schema: catalogo; Owner: postgres
--

ALTER TABLE ONLY catalogo.competencias
    ADD CONSTRAINT competencias_temporada_id_foreign FOREIGN KEY (temporada_id) REFERENCES catalogo.temporadas(id) ON DELETE SET NULL;


--
-- Name: equipos equipos_capitan_user_id_foreign; Type: FK CONSTRAINT; Schema: catalogo; Owner: postgres
--

ALTER TABLE ONLY catalogo.equipos
    ADD CONSTRAINT equipos_capitan_user_id_foreign FOREIGN KEY (capitan_user_id) REFERENCES seguridad.users(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: rondas rondas_categoria_id_foreign; Type: FK CONSTRAINT; Schema: catalogo; Owner: postgres
--

ALTER TABLE ONLY catalogo.rondas
    ADD CONSTRAINT rondas_categoria_id_foreign FOREIGN KEY (categoria_id) REFERENCES catalogo.categorias(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: notificaciones notificaciones_competencia_id_foreign; Type: FK CONSTRAINT; Schema: comunicacion; Owner: postgres
--

ALTER TABLE ONLY comunicacion.notificaciones
    ADD CONSTRAINT notificaciones_competencia_id_foreign FOREIGN KEY (competencia_id) REFERENCES catalogo.competencias(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: notificaciones notificaciones_user_id_foreign; Type: FK CONSTRAINT; Schema: comunicacion; Owner: postgres
--

ALTER TABLE ONLY comunicacion.notificaciones
    ADD CONSTRAINT notificaciones_user_id_foreign FOREIGN KEY (user_id) REFERENCES seguridad.users(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: clasificaciones clasificaciones_categoria_id_foreign; Type: FK CONSTRAINT; Schema: resultados; Owner: postgres
--

ALTER TABLE ONLY resultados.clasificaciones
    ADD CONSTRAINT clasificaciones_categoria_id_foreign FOREIGN KEY (categoria_id) REFERENCES catalogo.categorias(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: clasificaciones clasificaciones_equipo_id_foreign; Type: FK CONSTRAINT; Schema: resultados; Owner: postgres
--

ALTER TABLE ONLY resultados.clasificaciones
    ADD CONSTRAINT clasificaciones_equipo_id_foreign FOREIGN KEY (equipo_id) REFERENCES catalogo.equipos(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: incidencias incidencias_categoria_id_foreign; Type: FK CONSTRAINT; Schema: resultados; Owner: postgres
--

ALTER TABLE ONLY resultados.incidencias
    ADD CONSTRAINT incidencias_categoria_id_foreign FOREIGN KEY (categoria_id) REFERENCES catalogo.categorias(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: incidencias incidencias_equipo_id_foreign; Type: FK CONSTRAINT; Schema: resultados; Owner: postgres
--

ALTER TABLE ONLY resultados.incidencias
    ADD CONSTRAINT incidencias_equipo_id_foreign FOREIGN KEY (equipo_id) REFERENCES catalogo.equipos(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: incidencias incidencias_reportado_por_foreign; Type: FK CONSTRAINT; Schema: resultados; Owner: postgres
--

ALTER TABLE ONLY resultados.incidencias
    ADD CONSTRAINT incidencias_reportado_por_foreign FOREIGN KEY (reportado_por) REFERENCES seguridad.users(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: resultados resultados_equipo_id_foreign; Type: FK CONSTRAINT; Schema: resultados; Owner: postgres
--

ALTER TABLE ONLY resultados.resultados
    ADD CONSTRAINT resultados_equipo_id_foreign FOREIGN KEY (equipo_id) REFERENCES catalogo.equipos(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: resultados_hist resultados_hist_editado_por_foreign; Type: FK CONSTRAINT; Schema: resultados; Owner: postgres
--

ALTER TABLE ONLY resultados.resultados_hist
    ADD CONSTRAINT resultados_hist_editado_por_foreign FOREIGN KEY (editado_por) REFERENCES seguridad.users(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: resultados_hist resultados_hist_resultado_id_foreign; Type: FK CONSTRAINT; Schema: resultados; Owner: postgres
--

ALTER TABLE ONLY resultados.resultados_hist
    ADD CONSTRAINT resultados_hist_resultado_id_foreign FOREIGN KEY (resultado_id) REFERENCES resultados.resultados(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: resultados resultados_juez_user_id_foreign; Type: FK CONSTRAINT; Schema: resultados; Owner: postgres
--

ALTER TABLE ONLY resultados.resultados
    ADD CONSTRAINT resultados_juez_user_id_foreign FOREIGN KEY (juez_user_id) REFERENCES seguridad.users(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: resultados resultados_ronda_id_foreign; Type: FK CONSTRAINT; Schema: resultados; Owner: postgres
--

ALTER TABLE ONLY resultados.resultados
    ADD CONSTRAINT resultados_ronda_id_foreign FOREIGN KEY (ronda_id) REFERENCES catalogo.rondas(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: user_activation_tokens seguridad_user_activation_tokens_user_id_foreign; Type: FK CONSTRAINT; Schema: seguridad; Owner: postgres
--

ALTER TABLE ONLY seguridad.user_activation_tokens
    ADD CONSTRAINT seguridad_user_activation_tokens_user_id_foreign FOREIGN KEY (user_id) REFERENCES seguridad.users(id) ON DELETE CASCADE;


--
-- Name: sessions sessions_user_id_foreign; Type: FK CONSTRAINT; Schema: seguridad; Owner: postgres
--

ALTER TABLE ONLY seguridad.sessions
    ADD CONSTRAINT sessions_user_id_foreign FOREIGN KEY (user_id) REFERENCES seguridad.users(id) ON DELETE SET NULL;


--
-- Name: users users_role_id_foreign; Type: FK CONSTRAINT; Schema: seguridad; Owner: postgres
--

ALTER TABLE ONLY seguridad.users
    ADD CONSTRAINT users_role_id_foreign FOREIGN KEY (role_id) REFERENCES seguridad.roles(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: asignaciones_juez_categoria asignaciones_juez_categoria_categoria_id_foreign; Type: FK CONSTRAINT; Schema: vinculaciones; Owner: postgres
--

ALTER TABLE ONLY vinculaciones.asignaciones_juez_categoria
    ADD CONSTRAINT asignaciones_juez_categoria_categoria_id_foreign FOREIGN KEY (categoria_id) REFERENCES catalogo.categorias(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: asignaciones_juez_categoria asignaciones_juez_categoria_juez_user_id_foreign; Type: FK CONSTRAINT; Schema: vinculaciones; Owner: postgres
--

ALTER TABLE ONLY vinculaciones.asignaciones_juez_categoria
    ADD CONSTRAINT asignaciones_juez_categoria_juez_user_id_foreign FOREIGN KEY (juez_user_id) REFERENCES seguridad.users(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: inscripcion_integrantes inscripcion_integrantes_inscripcion_id_foreign; Type: FK CONSTRAINT; Schema: vinculaciones; Owner: postgres
--

ALTER TABLE ONLY vinculaciones.inscripcion_integrantes
    ADD CONSTRAINT inscripcion_integrantes_inscripcion_id_foreign FOREIGN KEY (inscripcion_id) REFERENCES vinculaciones.inscripciones(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: inscripcion_integrantes inscripcion_integrantes_user_id_foreign; Type: FK CONSTRAINT; Schema: vinculaciones; Owner: postgres
--

ALTER TABLE ONLY vinculaciones.inscripcion_integrantes
    ADD CONSTRAINT inscripcion_integrantes_user_id_foreign FOREIGN KEY (user_id) REFERENCES seguridad.users(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- Name: inscripciones inscripciones_categoria_id_foreign; Type: FK CONSTRAINT; Schema: vinculaciones; Owner: postgres
--

ALTER TABLE ONLY vinculaciones.inscripciones
    ADD CONSTRAINT inscripciones_categoria_id_foreign FOREIGN KEY (categoria_id) REFERENCES catalogo.categorias(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: inscripciones inscripciones_competencia_id_foreign; Type: FK CONSTRAINT; Schema: vinculaciones; Owner: postgres
--

ALTER TABLE ONLY vinculaciones.inscripciones
    ADD CONSTRAINT inscripciones_competencia_id_foreign FOREIGN KEY (competencia_id) REFERENCES catalogo.competencias(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: inscripciones inscripciones_equipo_id_foreign; Type: FK CONSTRAINT; Schema: vinculaciones; Owner: postgres
--

ALTER TABLE ONLY vinculaciones.inscripciones
    ADD CONSTRAINT inscripciones_equipo_id_foreign FOREIGN KEY (equipo_id) REFERENCES catalogo.equipos(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: inscripciones inscripciones_user_id_foreign; Type: FK CONSTRAINT; Schema: vinculaciones; Owner: postgres
--

ALTER TABLE ONLY vinculaciones.inscripciones
    ADD CONSTRAINT inscripciones_user_id_foreign FOREIGN KEY (user_id) REFERENCES seguridad.users(id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- PostgreSQL database dump complete
--

