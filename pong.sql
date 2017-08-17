--
-- PostgreSQL database dump
--

-- Dumped from database version 9.6.1
-- Dumped by pg_dump version 9.6.1

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner: 
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


SET search_path = public, pg_catalog;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: challenges; Type: TABLE; Schema: public; Owner: webuser
--

CREATE TABLE challenges (
    challenger character varying NOT NULL,
    defender character varying NOT NULL,
    accepted boolean,
    "time" timestamp with time zone NOT NULL
);


ALTER TABLE challenges OWNER TO webuser;

--
-- Name: games_id_seq; Type: SEQUENCE; Schema: public; Owner: webuser
--

CREATE SEQUENCE games_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE games_id_seq OWNER TO webuser;

--
-- Name: games; Type: TABLE; Schema: public; Owner: webuser
--

CREATE TABLE games (
    id integer DEFAULT nextval('games_id_seq'::regclass),
    player1 character varying NOT NULL,
    player2 character varying NOT NULL,
    winner character varying NOT NULL,
    match_result character varying,
    player1_old_elo integer,
    player2_old_elo integer,
    player1_new_elo integer,
    player2_new_elo integer,
    "time" timestamp with time zone DEFAULT now() NOT NULL
);


ALTER TABLE games OWNER TO webuser;

--
-- Name: tournament_games_id_seq; Type: SEQUENCE; Schema: public; Owner: webuser
--

CREATE SEQUENCE tournament_games_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE tournament_games_id_seq OWNER TO webuser;

--
-- Name: tournament_games; Type: TABLE; Schema: public; Owner: webuser
--

CREATE TABLE tournament_games (
    game_id integer DEFAULT nextval('tournament_games_id_seq'::regclass) NOT NULL,
    tournament_id integer NOT NULL,
    player1 character varying NOT NULL,
    player2 character varying NOT NULL,
    winner character varying,
    played_when timestamp with time zone DEFAULT now(),
    parent_game integer
);


ALTER TABLE tournament_games OWNER TO webuser;

--
-- Name: tournament_id_seq; Type: SEQUENCE; Schema: public; Owner: webuser
--

CREATE SEQUENCE tournament_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE tournament_id_seq OWNER TO webuser;

--
-- Name: tournament_players; Type: TABLE; Schema: public; Owner: webuser
--

CREATE TABLE tournament_players (
    tournament_id integer NOT NULL,
    name character varying NOT NULL
);


ALTER TABLE tournament_players OWNER TO webuser;

--
-- Name: tournaments; Type: TABLE; Schema: public; Owner: webuser
--

CREATE TABLE tournaments (
    tournament_name character varying,
    tournament_id integer DEFAULT nextval('tournament_id_seq'::regclass) NOT NULL,
    initialized timestamp with time zone DEFAULT now() NOT NULL,
    started timestamp with time zone,
    finished timestamp with time zone,
    winner character varying,
    creator character varying NOT NULL,
    active boolean DEFAULT true NOT NULL
);


ALTER TABLE tournaments OWNER TO webuser;

--
-- Name: users; Type: TABLE; Schema: public; Owner: webuser
--

CREATE TABLE users (
    name character varying NOT NULL,
    elo smallint DEFAULT 100 NOT NULL,
    pass character varying,
    comment character varying,
    games integer DEFAULT 0 NOT NULL,
    created timestamp with time zone DEFAULT now() NOT NULL,
    slack_uid character varying,
    admin boolean DEFAULT false NOT NULL,
    wins integer DEFAULT 0 NOT NULL,
    losses integer DEFAULT 0 NOT NULL
);


ALTER TABLE users OWNER TO webuser;

--
-- Name: users names_pkey; Type: CONSTRAINT; Schema: public; Owner: webuser
--

ALTER TABLE ONLY users
    ADD CONSTRAINT names_pkey PRIMARY KEY (name);


--
-- Name: tournament_games tournament_games_pkey; Type: CONSTRAINT; Schema: public; Owner: webuser
--

ALTER TABLE ONLY tournament_games
    ADD CONSTRAINT tournament_games_pkey PRIMARY KEY (game_id);


--
-- Name: tournament_players tournament_players_pkey; Type: CONSTRAINT; Schema: public; Owner: webuser
--

ALTER TABLE ONLY tournament_players
    ADD CONSTRAINT tournament_players_pkey PRIMARY KEY (tournament_id, name);


--
-- Name: tournaments tournaments_pkey; Type: CONSTRAINT; Schema: public; Owner: webuser
--

ALTER TABLE ONLY tournaments
    ADD CONSTRAINT tournaments_pkey PRIMARY KEY (tournament_id);


--
-- Name: fki_challenger_constraint; Type: INDEX; Schema: public; Owner: webuser
--

CREATE INDEX fki_challenger_constraint ON challenges USING btree (challenger);


--
-- Name: tournaments_active_idx; Type: INDEX; Schema: public; Owner: webuser
--

CREATE UNIQUE INDEX tournaments_active_idx ON tournaments USING btree (active) WHERE (active = true);


--
-- Name: challenges challenger_constraint; Type: FK CONSTRAINT; Schema: public; Owner: webuser
--

ALTER TABLE ONLY challenges
    ADD CONSTRAINT challenger_constraint FOREIGN KEY (challenger) REFERENCES users(name);


--
-- Name: challenges defender_constraint; Type: FK CONSTRAINT; Schema: public; Owner: webuser
--

ALTER TABLE ONLY challenges
    ADD CONSTRAINT defender_constraint FOREIGN KEY (challenger) REFERENCES users(name);


--
-- Name: tournament_players name_exists; Type: FK CONSTRAINT; Schema: public; Owner: webuser
--

ALTER TABLE ONLY tournament_players
    ADD CONSTRAINT name_exists FOREIGN KEY (name) REFERENCES users(name);


--
-- Name: games player1_exists; Type: FK CONSTRAINT; Schema: public; Owner: webuser
--

ALTER TABLE ONLY games
    ADD CONSTRAINT player1_exists FOREIGN KEY (player1) REFERENCES users(name);


--
-- Name: games player2_exists; Type: FK CONSTRAINT; Schema: public; Owner: webuser
--

ALTER TABLE ONLY games
    ADD CONSTRAINT player2_exists FOREIGN KEY (player2) REFERENCES users(name);

-- This is an easy way to keep track of active an inactive players.
CREATE FUNCTION update_last_update() RETURNS trigger AS $$
BEGIN
  NEW.last_update := NOW();

  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER
  update_last_update
BEFORE UPDATE ON
  users
FOR EACH ROW EXECUTE PROCEDURE
  update_last_update();

-- If you need to update the last_updated timestamps retroactively, use this:
--update users set last_update=(select max(time) from games where player1=users.name OR player2=users.name);

--
-- PostgreSQL database dump complete
--

